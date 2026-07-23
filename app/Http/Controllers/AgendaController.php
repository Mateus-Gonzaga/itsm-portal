<?php

namespace App\Http\Controllers;

use App\Data\PlanningEvent;
use App\Data\TicketData;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\AgendaTask;
use App\Models\KanbanCard;
use App\Models\User;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiPlanningRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        // Eventos = tarefas de chamado + prazos (GLPI) + tarefas livres (locais).
        $events = $this->planning->events($filters)->merge($this->localEvents($user));

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

        // Responsáveis = qualquer usuário do GLPI cujo perfil vira técnico/gestor
        // (mesma lógica do login, com fallback — pega até Super-Admin como o halley).
        $technicianUsers = $this->directory->users()
            ->filter(fn (array $u) => UserRole::fromGlpiProfile((string) ($u['profile'] ?? ''))->isStaff())
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
            // Quadros Kanban (equipe + atenção/urgente), agrupados por coluna.
            'kanban' => KanbanCard::where('board', 'equipe')->orderBy('position')->orderBy('id')->get()->groupBy('status'),
            'kanbanUrgente' => KanbanCard::where('board', 'urgente')->orderBy('position')->orderBy('id')->get()->groupBy('status'),
        ]);
    }

    /** Tarefas livres locais (com escopo por papel) como eventos do calendário. */
    private function localEvents(User $user): Collection
    {
        $tasks = AgendaTask::orderBy('start_at')->get();

        // Técnico vê as suas + as sem responsável (demandas da equipe); gestor vê tudo.
        if ($user->role === UserRole::Tecnico && $user->glpi_id) {
            $tasks = $tasks->filter(fn (AgendaTask $t) => $t->owner_glpi_id === null
                || (int) $t->owner_glpi_id === (int) $user->glpi_id);
        }

        return $tasks->map(fn (AgendaTask $t) => new PlanningEvent(
            id: 'atask-'.$t->id,
            ticketId: 0,
            title: $t->title,
            start: CarbonImmutable::parse($t->start_at),
            end: CarbonImmutable::parse($t->end_at),
            type: 'event',
            movable: true,
            technicianName: $t->owner_name,
            technicianId: $t->owner_glpi_id,
            done: (bool) $t->done,
            eventId: $t->id,
            seriesId: $t->series_id,
            description: $t->description,
            color: $t->color,
        ))->values();
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

    /**
     * Cria uma tarefa livre da equipe (local). Opção de recorrência: repetir em
     * dias da semana selecionados até uma data — gera uma ocorrência por dia.
     */
    public function storeEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'begin' => ['required', 'date'],
            'end' => ['required', 'date', 'after:begin'],
            'owner_glpi_id' => ['nullable', 'integer'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:20'],
            'repeat' => ['nullable', 'boolean'],
            'until' => ['nullable', 'date'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
        ]);

        $begin = CarbonImmutable::parse($data['begin']);
        $end = CarbonImmutable::parse($data['end']);
        $duration = max(1, $begin->diffInMinutes($end));
        $owner = ! empty($data['owner_glpi_id']) ? (int) $data['owner_glpi_id'] : null;

        $base = [
            'title' => $data['title'],
            'description' => $data['content'] ?? null,
            'color' => $data['color'] ?? null,
            'owner_glpi_id' => $owner,
            'owner_name' => $owner ? ($data['owner_name'] ?? null) : null,
            'created_by' => $request->user()->id,
        ];

        // Sem recorrência: uma única tarefa.
        if (! $request->boolean('repeat') || empty($data['until'])) {
            AgendaTask::create($base + ['start_at' => $begin, 'end_at' => $end]);

            return response()->json(['ok' => true, 'created' => 1]);
        }

        // Recorrência: uma ocorrência por dia da semana escolhido, até a data-limite.
        $until = CarbonImmutable::parse($data['until'])->endOfDay();
        $weekdays = ! empty($data['weekdays']) ? array_map('intval', $data['weekdays']) : range(0, 6);
        $series = (string) Str::uuid();
        $count = 0;
        for ($d = $begin; $d->lte($until) && $count < 180; $d = $d->addDay()) {
            if (! in_array($d->dayOfWeek, $weekdays, true)) {
                continue;
            }
            $s = $d->setTime($begin->hour, $begin->minute, 0);
            AgendaTask::create($base + [
                'series_id' => $series,
                'start_at' => $s,
                'end_at' => $s->addMinutes($duration),
            ]);
            $count++;
        }

        return response()->json(['ok' => true, 'created' => $count]);
    }

    /** Edita uma tarefa livre (título, detalhes, responsável, cor, horário). */
    public function updateEvent(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'begin' => ['required', 'date'],
            'end' => ['required', 'date', 'after:begin'],
            'owner_glpi_id' => ['nullable', 'integer'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:20'],
            'apply_series' => ['nullable', 'boolean'],
        ]);

        $task = AgendaTask::find($id);
        if (! $task) {
            return response()->json(['ok' => false], 404);
        }

        $owner = ! empty($data['owner_glpi_id']) ? (int) $data['owner_glpi_id'] : null;
        $comum = [
            'title' => $data['title'],
            'description' => $data['content'] ?? null,
            'color' => $data['color'] ?? null,
            'owner_glpi_id' => $owner,
            'owner_name' => $owner ? ($data['owner_name'] ?? null) : null,
        ];

        // "Aplicar a todos os dias": muda o conteúdo da série inteira, mas cada
        // ocorrência mantém a SUA data (só o horário do dia é replicado).
        if ($request->boolean('apply_series') && $task->series_id) {
            $begin = CarbonImmutable::parse($data['begin']);
            $end = CarbonImmutable::parse($data['end']);
            $duration = max(1, $begin->diffInMinutes($end));

            foreach (AgendaTask::where('series_id', $task->series_id)->get() as $t) {
                $s = CarbonImmutable::parse($t->start_at)->setTime($begin->hour, $begin->minute, 0);
                $t->update($comum + ['start_at' => $s, 'end_at' => $s->addMinutes($duration)]);
            }

            return response()->json(['ok' => true]);
        }

        $task->update($comum + [
            'start_at' => CarbonImmutable::parse($data['begin']),
            'end_at' => CarbonImmutable::parse($data['end']),
        ]);

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

        AgendaTask::where('id', (int) $data['event_id'])->update([
            'start_at' => CarbonImmutable::parse($data['begin']),
            'end_at' => CarbonImmutable::parse($data['end']),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Marca/desmarca a tarefa livre como concluída. */
    public function toggleEventDone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
            'done' => ['required', 'boolean'],
        ]);

        AgendaTask::where('id', (int) $data['event_id'])->update(['done' => (bool) $data['done']]);

        return response()->json(['ok' => true]);
    }

    /** Exclui uma tarefa livre (só a ocorrência). */
    public function destroyEvent(int $id): JsonResponse
    {
        AgendaTask::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Exclui a SÉRIE inteira (todas as ocorrências recorrentes). */
    public function destroyEventSeries(int $id): JsonResponse
    {
        $task = AgendaTask::find($id);
        if ($task && $task->series_id) {
            AgendaTask::where('series_id', $task->series_id)->delete();
        } elseif ($task) {
            $task->delete();
        }

        return response()->json(['ok' => true]);
    }
}
