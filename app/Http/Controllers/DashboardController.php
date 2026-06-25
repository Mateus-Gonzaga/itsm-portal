<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
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

        return view('dashboard.index', [
            'role' => $user->role,
            'tickets' => $list,
            'metrics' => $metrics,
            'subtitle' => $subtitle,
        ]);
    }
}
