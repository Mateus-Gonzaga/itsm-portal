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
        $tecnicoProfile = (string) config('portal.tecnico_profile', 'Técnico FL');
        $allUsers = $dir->users();

        // Técnicos = usuários com perfil Técnico FL (ou equivalente)
        $tecnicos = $allUsers
            ->filter(fn (array $u) => $u['profile'] === $tecnicoProfile)
            ->values();

        // Gestores = usuários com perfil Gestor / Admin / Super-Admin ou equipe interna (inclui Halley)
        $gestores = $allUsers
            ->filter(function (array $u) use ($tecnicoProfile) {
                if ($u['profile'] === $tecnicoProfile) {
                    return false;
                }
                $p = mb_strtolower($u['profile'] ?? '');
                $login = mb_strtolower($u['login'] ?? '');
                $name = mb_strtolower($u['name'] ?? '');

                return str_contains($p, 'gestor')
                    || str_contains($p, 'admin')
                    || str_contains($p, 'fourline')
                    || str_contains($login, 'halley')
                    || str_contains($name, 'halley');
            })
            ->values();

        $events = $planning->events();
        $now = CarbonImmutable::now();
        $closed = [TicketStatus::Solved, TicketStatus::Closed];

        $mapUser = function (array $t) use ($tickets, $events, $now, $closed) {
            $glpiId = (int) $t['id'];
            $tk = $glpiId > 0 ? $tickets->all(['technician_glpi_id' => $glpiId]) : collect();

            return [
                'id' => $glpiId,
                'name' => $t['name'],
                'login' => $t['login'],
                'active' => $t['active'],
                'profile' => $t['profile'],
                'profile_id' => $t['profile_id'] ?? 0,
                'entity_id' => $t['entity_id'],
                'entity' => $t['entity'],
                'recursive' => $t['recursive'],
                'atribuidos' => $tk->count(),
                'abertos' => $tk->reject(fn (TicketData $x) => in_array($x->status, $closed, true))->count(),
                'resolvidos' => $tk->filter(fn (TicketData $x) => in_array($x->status, $closed, true))->count(),
                'agendados' => $events->filter(fn (PlanningEvent $e) => $e->type === 'task'
                    && $e->technicianId === $glpiId && $e->start->gte($now))->count(),
            ];
        };

        $rowsTecnicos = $tecnicos->map($mapUser)->sortByDesc('abertos')->values();
        $rowsGestores = $gestores->map($mapUser)->sortByDesc('abertos')->values();
        $allStaff = $rowsTecnicos->concat($rowsGestores);

        $profiles = $dir->profiles();
        $gestorProfileId = $profiles->first(fn ($p) => str_contains(mb_strtolower($p['name']), 'gestor'))['id'] ?? 0;

        return view('modules.technicians', [
            'rows' => $rowsTecnicos,
            'rowsTecnicos' => $rowsTecnicos,
            'rowsGestores' => $rowsGestores,
            'stats' => [
                'tecnicos' => $rowsTecnicos->count(),
                'gestores' => $rowsGestores->count(),
                'atribuidos' => $allStaff->sum('atribuidos'),
                'abertos' => $allStaff->sum('abertos'),
                'agendados' => $allStaff->sum('agendados'),
            ],
            'entities' => $dir->entities(),
            'tecnicoProfileId' => $profiles->firstWhere('name', $tecnicoProfile)['id'] ?? 0,
            'gestorProfileId' => $gestorProfileId,
        ]);
    }
}
