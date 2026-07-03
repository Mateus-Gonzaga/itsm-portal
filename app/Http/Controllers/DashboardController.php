<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\KanbanCard;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GlpiTicketRepositoryInterface $tickets): View
    {
        $user = $request->user();

        // Cada perfil enxerga um recorte diferente dos chamados.
        // Passa nome (driver Fake) + glpi_id (driver Api filtra pelos atores).
        $list = match ($user->role) {
            UserRole::Cliente => $tickets->all(array_filter([
                'requester' => $user->name,
                'requester_glpi_id' => $user->glpi_id,
            ], fn ($v) => $v !== null)),
            UserRole::Tecnico => $tickets->all(array_filter([
                'technician' => $user->name,
                'technician_glpi_id' => $user->glpi_id,
            ], fn ($v) => $v !== null)),
            UserRole::Gestor => $tickets->all(),
        };

        $closed = [TicketStatus::Solved, TicketStatus::Closed];
        $total = $list->count();
        $solved = $list->filter(fn (TicketData $t) => in_array($t->status, $closed, true))->count();

        $metrics = [
            'total' => $total,
            'open' => $list->reject(fn (TicketData $t) => in_array($t->status, $closed, true))->count(),
            'overdue' => $list->filter(fn (TicketData $t) => $t->isOverdue())->count(),
            'solved' => $solved,
            'resolution' => $total > 0 ? (int) round($solved / $total * 100) : 0,
        ];

        $subtitle = match ($user->role) {
            UserRole::Cliente => 'Aqui está um resumo dos seus chamados',
            UserRole::Tecnico => 'Aqui está o seu painel de atendimento',
            UserRole::Gestor => 'Visão geral completa do atendimento',
        };

        // Distribuições por status/prioridade (só o que tem contagem > 0).
        $byStatus = collect(TicketStatus::cases())
            ->map(fn (TicketStatus $s) => ['label' => $s->label(), 'color' => $s->color(), 'count' => $list->where('status', $s)->count()])
            ->filter(fn ($r) => $r['count'] > 0)->values();

        $byPriority = collect(TicketPriority::cases())
            ->map(fn (TicketPriority $p) => ['label' => $p->label(), 'color' => $p->color(), 'count' => $list->where('priority', $p)->count()])
            ->filter(fn ($r) => $r['count'] > 0)->values();

        // Próximos prazos (chamados abertos com data-limite, mais urgentes primeiro).
        $upcoming = $list
            ->filter(fn (TicketData $t) => ! in_array($t->status, $closed, true) && $t->dueDate !== null)
            ->sortBy(fn (TicketData $t) => $t->dueDate)
            ->take(5)
            ->values();

        // Resumo do quadro Kanban da equipe (staff).
        $kanban = $user->role === UserRole::Cliente
            ? collect()
            : KanbanCard::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('dashboard.index', [
            'role' => $user->role,
            'tickets' => $list,
            'metrics' => $metrics,
            'subtitle' => $subtitle,
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'upcoming' => $upcoming,
            'kanban' => $kanban,
        ]);
    }
}
