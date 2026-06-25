<?php

namespace App\Http\Controllers;

use App\Data\PlanningEvent;
use App\Data\TicketData;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Glpi\GlpiPlanningRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(
        private readonly GlpiPlanningRepositoryInterface $planning,
        private readonly GlpiTicketRepositoryInterface $tickets,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        // Gestor vê todos (e filtra por técnico no front); técnico vê só os seus.
        $filters = $user->role === UserRole::Tecnico && $user->glpi_id
            ? ['technician_glpi_id' => $user->glpi_id]
            : [];

        $events = $this->planning->events($filters);

        // Lista de técnicos para o filtro (derivada dos próprios eventos).
        $technicians = $events
            ->filter(fn (PlanningEvent $e) => $e->technicianId !== null)
            ->map(fn (PlanningEvent $e) => ['id' => $e->technicianId, 'name' => $e->technicianName])
            ->unique('id')->sortBy('name')->values();

        // Dados do modal "Novo agendamento": chamados abertos + técnicos do portal.
        $openTickets = $this->tickets->all()
            ->reject(fn (TicketData $t) => in_array($t->status, [TicketStatus::Solved, TicketStatus::Closed], true))
            ->map(fn (TicketData $t) => ['id' => $t->id, 'label' => "#{$t->id} — {$t->title}"])
            ->values();

        $technicianUsers = User::query()
            ->whereIn('role', [UserRole::Tecnico, UserRole::Gestor])
            ->whereNotNull('glpi_id')
            ->orderBy('name')
            ->get(['name', 'glpi_id'])
            ->map(fn (User $u) => ['id' => $u->glpi_id, 'name' => $u->name]);

        return view('agenda.index', [
            'events' => $events->map(fn (PlanningEvent $e) => $e->toCalendar())->values(),
            'technicians' => $technicians,
            'canFilter' => $user->role === UserRole::Gestor,
            'openTickets' => $openTickets,
            'technicianUsers' => $technicianUsers,
            'selfTechId' => $user->glpi_id,
            'selfTechName' => $user->name,
            'isManager' => $user->role === UserRole::Gestor,
        ]);
    }

    public function reschedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'begin' => ['required', 'date'],
            'end' => ['required', 'date', 'after:begin'],
        ]);

        $this->planning->reschedule(
            (int) $data['task_id'],
            CarbonImmutable::parse($data['begin']),
            CarbonImmutable::parse($data['end']),
        );

        return response()->json(['ok' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'integer'],
            'technician_glpi_id' => ['required', 'integer'],
            'begin' => ['required', 'date'],
            'end' => ['required', 'date', 'after:begin'],
            'content' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->planning->schedule(
            (int) $data['ticket_id'],
            (int) $data['technician_glpi_id'],
            CarbonImmutable::parse($data['begin']),
            CarbonImmutable::parse($data['end']),
            $data['content'] ?? null,
        );

        return response()->json(['ok' => true]);
    }
}
