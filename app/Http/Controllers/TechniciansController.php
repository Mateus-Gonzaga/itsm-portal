<?php

namespace App\Http\Controllers;

use App\Data\PlanningEvent;
use App\Data\TicketData;
use App\Enums\TicketStatus;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiPlanningRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechniciansController extends Controller
{
    public function __invoke(
        Request $request,
        GlpiDirectoryRepositoryInterface $dir,
        GlpiTicketRepositoryInterface $tickets,
        GlpiPlanningRepositoryInterface $planning,
    ): View {
        // Técnicos = usuários com o perfil da equipe técnica (config/portal.php).
        $tecnicoProfile = (string) config('portal.tecnico_profile', 'Técnico FL');
        $tecnicos = $dir->users()
            ->filter(fn (array $u) => $u['profile'] === $tecnicoProfile)
            ->values();

        $events = $planning->events();
        $now = CarbonImmutable::now();
        $closed = [TicketStatus::Solved, TicketStatus::Closed];

        $rows = $tecnicos->map(function (array $t) use ($tickets, $events, $now, $closed) {
            $glpiId = (int) $t['id'];
            $tk = $glpiId > 0 ? $tickets->all(['technician_glpi_id' => $glpiId]) : collect();

            return [
                'id' => $glpiId,
                'name' => $t['name'],
                'login' => $t['login'],
                'active' => $t['active'],
                'entity_id' => $t['entity_id'],
                'entity' => $t['entity'],
                'recursive' => $t['recursive'],
                'atribuidos' => $tk->count(),
                'abertos' => $tk->reject(fn (TicketData $x) => in_array($x->status, $closed, true))->count(),
                'resolvidos' => $tk->filter(fn (TicketData $x) => in_array($x->status, $closed, true))->count(),
                'agendados' => $events->filter(fn (PlanningEvent $e) => $e->type === 'task'
                    && $e->technicianId === $glpiId && $e->start->gte($now))->count(),
            ];
        })->sortByDesc('abertos')->values();

        $profiles = $dir->profiles();

        return view('modules.technicians', [
            'rows' => $rows,
            'stats' => [
                'tecnicos' => $rows->count(),
                'atribuidos' => $rows->sum('atribuidos'),
                'abertos' => $rows->sum('abertos'),
                'agendados' => $rows->sum('agendados'),
            ],
            'entities' => $dir->entities(),
            'tecnicoProfileId' => $profiles->firstWhere('name', $tecnicoProfile)['id'] ?? 0,
        ]);
    }
}
