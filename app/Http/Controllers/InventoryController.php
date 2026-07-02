<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
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

        $msg = $data['itemtype'] === 'Computer'
            ? 'Computador e itens conectados movidos para a nova entidade.'
            : 'Ativo movido para a nova entidade.';

        return back()->with('status', $msg);
    }
}
