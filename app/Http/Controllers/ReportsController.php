<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Repositories\Glpi\GlpiInventoryRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __invoke(
        Request $request,
        GlpiTicketRepositoryInterface $tickets,
        GlpiInventoryRepositoryInterface $inventory,
    ): View {
        $all = $tickets->all(); // visão do gestor: todos os chamados

        $closed = [TicketStatus::Solved, TicketStatus::Closed];
        $total = $all->count();
        $resolvidos = $all->filter(fn (TicketData $t) => in_array($t->status, $closed, true))->count();

        // Distribuições (mantém a ordem dos enums / top clientes por volume).
        $byStatus = collect(TicketStatus::cases())
            ->map(fn (TicketStatus $s) => ['label' => $s->label(), 'color' => $s->color(), 'count' => $all->where('status', $s)->count()])
            ->filter(fn ($r) => $r['count'] > 0)->values();

        $byPriority = collect(TicketPriority::cases())
            ->map(fn (TicketPriority $p) => ['label' => $p->label(), 'color' => $p->color(), 'count' => $all->where('priority', $p)->count()])
            ->filter(fn ($r) => $r['count'] > 0)->values();

        $byClient = $all->groupBy('entity')
            ->map(fn ($g, $name) => ['label' => $name ?: '—', 'count' => $g->count()])
            ->sortByDesc('count')->take(10)->values();

        // --- RELATÓRIO DE MARCAS (FABRICANTES DE EQUIPAMENTOS) ---
        // Garante escopo adequado para gestor caso não esteja na sessão
        if (! session()->has('glpi_entity')) {
            session(['glpi_entity' => 0, 'glpi_entity_recursive' => true]);
        }

        try {
            $assets = $inventory->assets();
        } catch (\Throwable $e) {
            Log::warning('ReportsController: falha ao carregar ativos do inventário: '.$e->getMessage());
            $assets = collect();
        }

        $totalAssets = $assets->count();

        $normalizeMarca = function ($m) {
            $val = trim((string) $m);
            if ($val === '' || $val === '0' || $val === '—' || strtolower($val) === 'null' || strtolower($val) === 'genérico') {
                return 'Outras / Não informada';
            }

            return html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };

        $byMarca = $assets->groupBy(fn (array $a) => $normalizeMarca($a['manufacturer'] ?? null))
            ->map(function (Collection $group, string $marca) use ($totalAssets) {
                $count = $group->count();
                $tipos = $group->groupBy('type')->map->count()->all();
                $clientes = $group->pluck('entity')->unique()->filter()->values()->all();
                $modelos = $group->pluck('model')->filter(fn ($m) => $m && $m !== '—')->unique()->take(4)->values()->all();
                $pct = $totalAssets > 0 ? round(($count / $totalAssets) * 100, 1) : 0;

                return [
                    'marca' => $marca,
                    'count' => $count,
                    'pct' => $pct,
                    'tipos' => $tipos,
                    'clientes' => $clientes,
                    'modelos' => $modelos,
                ];
            })
            ->sortByDesc('count')
            ->values();

        $marcasValidas = $byMarca->reject(fn (array $m) => $m['marca'] === 'Outras / Não informada');
        $marcaLider = $marcasValidas->first();
        $semMarca = $byMarca->firstWhere('marca', 'Outras / Não informada')['count'] ?? 0;

        $metricasMarcas = [
            'total_ativos' => $totalAssets,
            'total_marcas' => $marcasValidas->count(),
            'marca_lider' => $marcaLider['marca'] ?? '—',
            'marca_lider_count' => $marcaLider['count'] ?? 0,
            'sem_marca' => $semMarca,
        ];

        return view('modules.reports', [
            'metrics' => [
                'total' => $total,
                'abertos' => $all->reject(fn (TicketData $t) => in_array($t->status, $closed, true))->count(),
                'resolvidos' => $resolvidos,
                'atrasados' => $all->filter(fn (TicketData $t) => $t->isOverdue())->count(),
                'resolucao' => $total > 0 ? (int) round($resolvidos / $total * 100) : 0,
            ],
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'byClient' => $byClient,
            'total' => $total,

            // Dados da aba Marcas
            'byMarca' => $byMarca,
            'metricasMarcas' => $metricasMarcas,
        ]);
    }
}
