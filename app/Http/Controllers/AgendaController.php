<?php

namespace App\Http\Controllers;

use App\Data\PlanningEvent;
use App\Data\TicketData;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
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
        private readonly GlpiDirectoryRepositoryInterface $directory,
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

        // Responsáveis = técnicos/gestores DIRETO do GLPI (não só quem já logou
        // no portal), para que qualquer técnico possa ser atribuído.
        $roleMap = (array) config('portal.profile_roles', []);
        $technicianUsers = $this->directory->users()
            ->filter(fn (array $u) => in_array($roleMap[$u['profile']] ?? '', ['tecnico', 'gestor'], true))
            ->map(fn (array $u) => ['id' => (int) $u['id'], 'name' => $u['name']])
            ->unique('id')->sortBy('name')->values();

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

    /** Cria uma tarefa livre da equipe (PlanningExternalEvent). */
    public function storeEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'begin' => ['required', 'date'],
            'end' => ['required', 'date', 'after:begin'],
            'owner_glpi_id' => ['nullable', 'integer'],
            'content' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->planning->createEvent(
            $data['title'],
            CarbonImmutable::parse($data['begin']),
            CarbonImmutable::parse($data['end']),
            ! empty($data['owner_glpi_id']) ? (int) $data['owner_glpi_id'] : null,
            $data['content'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    /** Remarca uma tarefa livre (arrastar/redimensionar). */
    public function rescheduleEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
            'begin' => ['required', 'date'],
            'end' => ['required', 'date', 'after:begin'],
        ]);

        $this->planning->rescheduleEvent(
            (int) $data['event_id'],
            CarbonImmutable::parse($data['begin']),
            CarbonImmutable::parse($data['end']),
        );

        return response()->json(['ok' => true]);
    }

    /** Marca/desmarca a tarefa livre como concluída. */
    public function toggleEventDone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
            'done' => ['required', 'boolean'],
        ]);

        $this->planning->setEventDone((int) $data['event_id'], (bool) $data['done']);

        return response()->json(['ok' => true]);
    }

    /** Exclui uma tarefa livre. */
    public function destroyEvent(int $id): JsonResponse
    {
        $this->planning->deleteEvent($id);

        return response()->json(['ok' => true]);
    }
}
