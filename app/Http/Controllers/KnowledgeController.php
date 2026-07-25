<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArticle;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Base de Conhecimento: fichas por cliente/filial (contrato, ativos, etc.). */
class KnowledgeController extends Controller
{
    public function index(Request $request, GlpiDirectoryRepositoryInterface $dir): View
    {
        $q = trim((string) $request->string('q'));
        $cat = (string) $request->string('categoria');

        $artigos = KnowledgeArticle::query()
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

        return view('modules.knowledge', [
            'artigos' => $artigos,
            'categorias' => KnowledgeArticle::CATEGORIAS,
            'clientes' => $clientes,
            'q' => $q,
            'catSel' => $cat,
            'total' => KnowledgeArticle::count(),
            'valorTotal' => (float) KnowledgeArticle::sum('valor'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        KnowledgeArticle::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Registro salvo na base de conhecimento.');
    }

    public function update(Request $request, KnowledgeArticle $artigo): RedirectResponse
    {
        $artigo->update($this->validated($request));

        return back()->with('status', 'Registro atualizado.');
    }

    public function destroy(KnowledgeArticle $artigo): RedirectResponse
    {
        $artigo->delete();

        return back()->with('status', 'Registro excluído.');
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
