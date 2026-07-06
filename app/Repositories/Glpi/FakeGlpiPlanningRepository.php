<?php

namespace App\Repositories\Glpi;

use App\Data\PlanningEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Agenda de MENTIRA (modo demo, sem GLPI). Gera eventos a partir de um seed e
 * guarda as remarcações em cache, para o arrastar/editar persistir entre
 * requisições — igual ao FakeGlpiTicketRepository.
 */
class FakeGlpiPlanningRepository implements GlpiPlanningRepositoryInterface
{
    private const OVERRIDES_KEY = 'fake_planning_overrides';

    private const CREATED_KEY = 'fake_planning_created';

    private const EVENTS_KEY = 'fake_planning_events';

    public function events(array $filters = []): Collection
    {
        $overrides = cache()->get(self::OVERRIDES_KEY, []);
        $techId = ! empty($filters['technician_glpi_id']) ? (int) $filters['technician_glpi_id'] : null;

        $events = $this->seed()->merge($this->created())->map(function (PlanningEvent $e) use ($overrides) {
            if ($e->taskId === null || ! isset($overrides[$e->taskId])) {
                return $e;
            }
            [$begin, $end] = $overrides[$e->taskId];

            return new PlanningEvent(
                id: $e->id, ticketId: $e->ticketId, title: $e->title,
                start: CarbonImmutable::parse($begin), end: CarbonImmutable::parse($end),
                type: $e->type, movable: $e->movable,
                technicianName: $e->technicianName, technicianId: $e->technicianId,
                taskId: $e->taskId, done: $e->done,
            );
        });

        if ($techId !== null) {
            $scope = $events->where('type', 'task')->where('technicianId', $techId);
            $ticketIds = $scope->pluck('ticketId')->unique()->all();
            $events = $events->filter(fn (PlanningEvent $e) => $e->type === 'task'
                ? $e->technicianId === $techId
                : in_array($e->ticketId, $ticketIds, true));
        }

        // Tarefas livres agora são locais (tabela agenda_tasks) — não entram aqui.
        return $events->values();
    }

    public function reschedule(int $taskId, CarbonImmutable $begin, CarbonImmutable $end): void
    {
        $overrides = cache()->get(self::OVERRIDES_KEY, []);
        $overrides[$taskId] = [$begin->toIso8601String(), $end->toIso8601String()];
        cache()->forever(self::OVERRIDES_KEY, $overrides);
    }

    public function schedule(int $ticketId, int $technicianGlpiId, CarbonImmutable $begin, CarbonImmutable $end, ?string $content = null): void
    {
        $created = cache()->get(self::CREATED_KEY, []);
        $taskId = 900 + count($created) + 1;
        $created[] = [
            'taskId' => $taskId,
            'ticketId' => $ticketId,
            'technicianId' => $technicianGlpiId,
            'begin' => $begin->toIso8601String(),
            'end' => $end->toIso8601String(),
        ];
        cache()->forever(self::CREATED_KEY, $created);
    }

    public function createEvent(string $title, CarbonImmutable $begin, CarbonImmutable $end, ?int $ownerGlpiId = null, ?string $content = null): void
    {
        $events = cache()->get(self::EVENTS_KEY, []);
        $eventId = 500 + count($events) + 1;
        $events[] = [
            'eventId' => $eventId,
            'title' => $title,
            'begin' => $begin->toIso8601String(),
            'end' => $end->toIso8601String(),
            'ownerId' => $ownerGlpiId,
            'done' => false,
        ];
        cache()->forever(self::EVENTS_KEY, $events);
    }

    public function rescheduleEvent(int $eventId, CarbonImmutable $begin, CarbonImmutable $end): void
    {
        $this->mutateEvent($eventId, function (array $e) use ($begin, $end) {
            $e['begin'] = $begin->toIso8601String();
            $e['end'] = $end->toIso8601String();

            return $e;
        });
    }

    public function setEventDone(int $eventId, bool $done): void
    {
        $this->mutateEvent($eventId, function (array $e) use ($done) {
            $e['done'] = $done;

            return $e;
        });
    }

    public function deleteEvent(int $eventId): void
    {
        $events = collect(cache()->get(self::EVENTS_KEY, []))
            ->reject(fn (array $e) => (int) $e['eventId'] === $eventId)
            ->values()->all();
        cache()->forever(self::EVENTS_KEY, $events);
    }

    /** Aplica uma transformação a um evento livre e regrava o cache. */
    private function mutateEvent(int $eventId, callable $fn): void
    {
        $events = collect(cache()->get(self::EVENTS_KEY, []))
            ->map(fn (array $e) => (int) $e['eventId'] === $eventId ? $fn($e) : $e)
            ->all();
        cache()->forever(self::EVENTS_KEY, $events);
    }

    /** @return Collection<int, PlanningEvent> Tarefas livres criadas na tela (demo). */
    private function externalEvents(): Collection
    {
        $names = [4 => 'Tiago Técnico', 2 => 'Gestor Demo'];

        return collect(cache()->get(self::EVENTS_KEY, []))->map(fn (array $e) => new PlanningEvent(
            id: 'ext-'.$e['eventId'],
            ticketId: 0,
            title: $e['title'],
            start: CarbonImmutable::parse($e['begin']),
            end: CarbonImmutable::parse($e['end']),
            type: 'event',
            movable: true,
            technicianName: $e['ownerId'] !== null ? ($names[$e['ownerId']] ?? 'Responsável') : null,
            technicianId: $e['ownerId'],
            done: (bool) ($e['done'] ?? false),
            eventId: (int) $e['eventId'],
        ));
    }

    /** @return Collection<int, PlanningEvent> Tarefas agendadas pela tela (demo). */
    private function created(): Collection
    {
        $techNames = [4 => 'Tiago Técnico', 2 => 'Gestor Demo'];

        return collect(cache()->get(self::CREATED_KEY, []))->map(fn (array $c) => new PlanningEvent(
            id: 'task-'.$c['taskId'],
            ticketId: $c['ticketId'],
            title: "#{$c['ticketId']} Atendimento agendado",
            start: CarbonImmutable::parse($c['begin']),
            end: CarbonImmutable::parse($c['end']),
            type: 'task',
            movable: true,
            technicianName: $techNames[$c['technicianId']] ?? 'Técnico',
            technicianId: $c['technicianId'],
            taskId: $c['taskId'],
        ));
    }

    /** @return Collection<int, PlanningEvent> */
    private function seed(): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();

        return collect([
            new PlanningEvent(id: 'task-101', ticketId: 2, title: '#2 Sistema ERP lento', start: $today->addHours(9), end: $today->addHours(11), type: 'task', movable: true, technicianName: 'Tiago Técnico', technicianId: 4, taskId: 101),
            new PlanningEvent(id: 'task-102', ticketId: 5, title: '#5 VPN não conecta', start: $today->addDay()->addHours(14), end: $today->addDay()->addHours(15), type: 'task', movable: true, technicianName: 'Tiago Técnico', technicianId: 4, taskId: 102),
            new PlanningEvent(id: 'task-103', ticketId: 3, title: '#3 Solicitar acesso ao e-mail', start: $today->addDays(2)->addHours(10), end: $today->addDays(2)->addHours(10)->addMinutes(30), type: 'task', movable: true, technicianName: 'Tiago Técnico', technicianId: 4, taskId: 103, done: true),
            new PlanningEvent(id: 'sla-5', ticketId: 5, title: 'Prazo: #5 VPN não conecta', start: $today->addHours(16), end: null, type: 'sla', movable: false),
            new PlanningEvent(id: 'sla-3', ticketId: 3, title: 'Prazo: #3 Acesso ao e-mail', start: $today->addDay()->addHours(18), end: null, type: 'sla', movable: false),
        ]);
    }
}
