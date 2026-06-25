<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __invoke(Request $request, GlpiTicketRepositoryInterface $tickets): View
    {
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
        ]);
    }
}
