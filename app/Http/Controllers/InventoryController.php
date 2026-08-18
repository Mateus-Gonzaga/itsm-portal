<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AssetValue;
use App\Models\AuditLog;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiInventoryRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(
        Request $request,
        GlpiInventoryRepositoryInterface $inventory,
        GlpiDirectoryRepositoryInterface $directory,
    ): View {
        $assets = $inventory->assets();
        $isManager = $request->user()->role === UserRole::Gestor;

        // Junta os valores (guardados no portal) a cada ativo por itemtype+id.
        $valores = AssetValue::get()->keyBy(fn (AssetValue $v) => $v->itemtype.'-'.$v->item_id);
        $assets = $assets->map(function (array $a) use ($valores) {
            $meta = $valores->get(($a['typeKey'] ?? '').'-'.($a['id'] ?? 0));
            $a['value'] = optional($meta)->value;
            $a['tag'] = optional($meta)->tag;

            return $a;
        });

        // Contagem por tipo (na ordem dos tipos suportados).
        $counts = collect($inventory->types())
            ->map(fn ($cfg, $key) => [
                'label' => $cfg['label'],
                'icon' => $cfg['icon'],
                'count' => $assets->where('typeKey', $key)->count(),
            ])
            ->values();

        return view('modules.inventory', [
            'assets' => $assets,
            'counts' => $counts,
            'total' => $assets->count(),
            'isManager' => $isManager,
            // Só o gestor edita a entidade do ativo; lista para o seletor.
            'entities' => $isManager ? $directory->entities() : collect(),
            'valorTotal' => (float) $assets->sum(fn (array $a) => (float) ($a['value'] ?? 0)),
        ]);
    }

    /** Define/limpa etiqueta e valor de um ativo (gestor) — no portal E o valor no GLPI (Infocom). */
    public function setValue(Request $request, GlpiInventoryRepositoryInterface $inventory): RedirectResponse
    {
        $data = $request->validate([
            'itemtype' => ['required', 'string', 'max:60'],
            'id' => ['required', 'integer', 'min:1'],
            'tag' => ['nullable', 'string', 'max:60'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $value = $data['value'] !== null ? (float) $data['value'] : null;
        $tag = ! empty($data['tag']) ? trim($data['tag']) : null;

        // 1) Guarda no portal (fonte rápida para exibir/somar). Sem valor E sem etiqueta = remove a linha.
        if ($value === null && $tag === null) {
            AssetValue::where('itemtype', $data['itemtype'])->where('item_id', (int) $data['id'])->delete();
        } else {
            AssetValue::updateOrCreate(
                ['itemtype' => $data['itemtype'], 'item_id' => (int) $data['id']],
                ['tag' => $tag, 'value' => $value],
            );
        }

        // 2) Espelha o valor no GLPI (Infocom/aba Gestão) — best-effort (pode faltar direito).
        try {
            $inventory->setInfocomValue($data['itemtype'], (int) $data['id'], $value);
        } catch (\Throwable $e) {
            return back()->with('error', 'Dados salvos no portal, mas não foi possível gravar o valor no GLPI (verifique o direito de "Informações financeiras" da conta de serviço): '.$e->getMessage());
        }

        AuditLog::record('inventory.value', "Atualizou ativo {$data['itemtype']} #{$data['id']} — etiqueta: ".($tag ?? '—').', valor: '.($value === null ? '—' : 'R$ '.number_format($value, 2, ',', '.')));

        return back()->with('status', 'Ativo atualizado (etiqueta/valor).');
    }

    /** Relatório de inventário para impressão/PDF (com bloco de assinaturas FL + cliente). */
    public function report(Request $request, GlpiInventoryRepositoryInterface $inventory): View
    {
        $assets = $inventory->assets();
        $valores = AssetValue::get()->keyBy(fn (AssetValue $v) => $v->itemtype.'-'.$v->item_id);
        $assets = $assets->map(function (array $a) use ($valores) {
            $meta = $valores->get(($a['typeKey'] ?? '').'-'.($a['id'] ?? 0));
            $a['value'] = optional($meta)->value;
            $a['tag'] = optional($meta)->tag;

            return $a;
        });

        // Filtro opcional por entidade (uma loja por relatório é o uso típico).
        $entidade = trim((string) $request->string('entidade'));
        if ($entidade !== '') {
            $assets = $assets->where('entity', $entidade)->values();
        }

        return view('modules.inventory-report', [
            'assets' => $assets->sortBy([['entity', false], ['type', false], ['name', false]])->values(),
            'entidade' => $entidade,
            'valorTotal' => (float) $assets->sum(fn (array $a) => (float) ($a['value'] ?? 0)),
            'geradoPor' => $request->user()->name,
            'geradoEm' => now(),
        ]);
    }

    /** Move um ativo para outra entidade do GLPI (gestor). */
    public function move(Request $request, GlpiInventoryRepositoryInterface $inventory): RedirectResponse
    {
        $data = $request->validate([
            'itemtype' => ['required', 'string', 'max:40'],
            'id' => ['required', 'integer', 'min:1'],
            'entity_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $inventory->moveAsset($data['itemtype'], (int) $data['id'], (int) $data['entity_id']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Não foi possível mover o ativo: '.$e->getMessage());
        }

        AuditLog::record('inventory.move', "Moveu ativo {$data['itemtype']} #{$data['id']} para entidade #{$data['entity_id']}");

        $msg = $data['itemtype'] === 'Computer'
            ? 'Computador e itens conectados movidos para a nova entidade.'
            : 'Ativo movido para a nova entidade.';

        return back()->with('status', $msg);
    }
}
