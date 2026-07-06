<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * DTO NEUTRO de um evento da agenda.
 *
 * Representa uma TAREFA agendada do chamado (TicketTask — móvel), o PRAZO de
 * SLA do chamado (data-limite — fixo) OU uma TAREFA LIVRE da equipe
 * (PlanningExternalEvent — demanda sem chamado, móvel). Igual ao TicketData:
 * nem o Fake nem o Api expõem o formato do GLPI acima desta camada.
 */
final class PlanningEvent
{
    public function __construct(
        public readonly string $id,           // 'task-12', 'sla-9' ou 'ext-7' (único no calendário)
        public readonly int|string $ticketId, // chamado relacionado (0 na tarefa livre)
        public readonly string $title,
        public readonly CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
        public readonly string $type,         // 'task' | 'sla' | 'event'
        public readonly bool $movable,        // arrastar/remarcar salva no GLPI?
        public readonly ?string $technicianName = null,
        public readonly ?int $technicianId = null,
        public readonly ?int $taskId = null,  // id da TicketTask (só quando type=task)
        public readonly bool $done = false,
        public readonly ?int $eventId = null, // id da tarefa livre local (só type=event)
        public readonly ?string $seriesId = null, // agrupa ocorrências recorrentes
    ) {
    }

    /** Formato consumido pelo FullCalendar no front. */
    public function toCalendar(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'start' => $this->start->toIso8601String(),
            'end' => $this->end?->toIso8601String(),
            'editable' => $this->movable,
            'classNames' => [
                match ($this->type) { 'sla' => 'ev-sla', 'event' => 'ev-event', default => 'ev-task' },
                $this->done ? 'ev-done' : '',
            ],
            'extendedProps' => [
                'type' => $this->type,
                'ticketId' => $this->ticketId,
                'taskId' => $this->taskId,
                'eventId' => $this->eventId,
                'technicianId' => $this->technicianId,
                'technicianName' => $this->technicianName,
                'done' => $this->done,
                'seriesId' => $this->seriesId,
            ],
        ];
    }
}
