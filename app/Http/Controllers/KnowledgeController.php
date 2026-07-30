<?php

namespace App\Http\Controllers;

use App\Models\AssetValue;
use App\Models\AuditLog;
use App\Models\KbAttachment;
use App\Models\KnowledgeArticle;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiInventoryRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Base de Conhecimento: fichas por cliente/filial (contrato, ativos, etc.). */
class KnowledgeController extends Controller
{
    public function index(Request $request, GlpiDirectoryRepositoryInterface $dir, GlpiInventoryRepositoryInterface $inventory): View
    {
        $q = trim((string) $request->string('q'));
        $cat = (string) $request->string('categoria');

        $artigos = KnowledgeArticle::with('attachments')
            ->when($q !== '', fn ($qb) => $qb->where(fn ($w) => $w
                ->where('titulo', 'like', "%{$q}%")
                ->orWhere('cliente', 'like', "%{$q}%")
                ->orWhere('conteudo', 'like', "%{$q}%")))
            ->when(in_array($cat, KnowledgeArticle::CATEGORIAS, true), fn ($qb) => $qb->where('categoria', $cat))
            ->orderBy('cliente')->orderByDesc('updated_at')
            ->get();

        // Sugestões de cliente (entidades do GLPI sob CLIENTES) para o formulário.
        $clientes = $dir->users()->pluck('entity')->merge(
            $dir->entities()
                ->filter(fn ($e) => str_contains(html_entity_decode((string) $e['completename']), 'CLIENTES'))
                ->pluck('name')
        )->filter()->unique()->sort()->values();

        // Valor dos ativos por entidade (do inventário) — pra auto-preencher a KB.
        $ativosPorEntidade = collect();
        try {
            $valores = AssetValue::get()->keyBy(fn (AssetValue $v) => $v->itemtype.'-'.$v->item_id);
            $ativosPorEntidade = $inventory->assets()
                ->map(fn (array $a) => [
                    'entity' => (string) ($a['entity'] ?? ''),
                    'v' => (float) optional($valores->get(($a['typeKey'] ?? '').'-'.($a['id'] ?? 0)))->value,
                ])
                ->groupBy('entity')
                ->map(fn ($g) => round($g->sum('v'), 2))
                ->filter(fn ($v) => $v > 0);
        } catch (\Throwable) {
            // sem inventário disponível -> segue sem os totais
        }

        return view('modules.knowledge', [
            'artigos' => $artigos,
            'categorias' => KnowledgeArticle::CATEGORIAS,
            'clientes' => $clientes,
            'ativosPorEntidade' => $ativosPorEntidade,
            'q' => $q,
            'catSel' => $cat,
            'total' => KnowledgeArticle::count(),
            'valorTotal' => (float) KnowledgeArticle::sum('valor'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $artigo = KnowledgeArticle::create($this->validated($request) + ['created_by' => $request->user()->id]);
        $this->saveFiles($request, $artigo);

        return back()->with('status', 'Registro salvo na base de conhecimento.');
    }

    public function update(Request $request, KnowledgeArticle $artigo): RedirectResponse
    {
        $artigo->update($this->validated($request));
        $this->saveFiles($request, $artigo);

        return back()->with('status', 'Registro atualizado.');
    }

    public function destroy(KnowledgeArticle $artigo): RedirectResponse
    {
        AuditLog::record('kb.destroy', "Excluiu registro KB #{$artigo->id} \"{$artigo->titulo}\" ({$artigo->cliente})");
        $artigo->delete(); // cascade + boot deleting apaga os arquivos

        return back()->with('status', 'Registro excluído.');
    }

    /** Salva os anexos enviados (contrato em PDF, imagem, etc.). */
    private function saveFiles(Request $request, KnowledgeArticle $artigo): void
    {
        // Só PDF e imagens (evita doc/xls com macros). 15 MB por arquivo.
        $request->validate([
            'files' => ['nullable', 'array', 'max:8'],
            'files.*' => ['file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp,image/gif', 'max:15360'],
        ]);

        foreach ($request->file('files', []) as $file) {
            $path = $file->store('kb', 'local');
            $artigo->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
            ]);
        }
    }

    /** Serve o anexo inline (imagens aparecem; PDF/office abre/baixa). */
    public function showAttachment(KbAttachment $anexo): Response
    {
        abort_unless(Storage::disk('local')->exists($anexo->path), 404);

        return response(Storage::disk('local')->get($anexo->path), 200, [
            'Content-Type' => $anexo->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($anexo->original_name).'"',
        ]);
    }

    public function destroyAttachment(KbAttachment $anexo): RedirectResponse
    {
        $anexo->delete();

        return back()->with('status', 'Anexo removido.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'cliente' => ['nullable', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(KnowledgeArticle::CATEGORIAS)],
            'titulo' => ['required', 'string', 'max:255'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'conteudo' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
