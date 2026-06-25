<?php

namespace App\Http\Controllers;

use App\Repositories\Glpi\GlpiInventoryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __invoke(Request $request, GlpiInventoryRepositoryInterface $inventory): View
    {
        $assets = $inventory->assets();

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
            'isManager' => $request->user()->role === \App\Enums\UserRole::Gestor,
        ]);
    }
}
