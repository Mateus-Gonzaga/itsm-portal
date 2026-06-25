<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * DTO NEUTRO de um evento da agenda.
 *
 * Representa tanto uma TAREFA agendada do chamado (TicketTask — móvel, pode
 * remarcar) quanto o PRAZO de SLA do chamado (data-limite — fixo). Igual ao
 * TicketData: nem o Fake nem o Api expõem o formato do GLPI acima desta camada.
 */
final class PlanningEvent
{
    public function __construct(
        public readonly string $id,           // 'task-12' ou 'sla-9' (único no calendário)
        public readonly int|string $ticketId, // chamado relacionado
        public readonly string $title,
        public readonly CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
        public readonly string $type,         // 'task' | 'sla'
        public readonly bool $movable,        // arrastar/remarcar salva no GLPI?
        public readonly ?string $technicianName = null,
        public readonly ?int $technicianId = null,
        public readonly ?int $taskId = null,  // id da TicketTask (só quando type=task)
        public readonly bool $done = false,
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
            'classNames' => [$this->type === 'sla' ? 'ev-sla' : 'ev-task', $this->done ? 'ev-done' : ''],
            'extendedProps' => [
                'type' => $this->type,
                'ticketId' => $this->ticketId,
                'taskId' => $this->taskId,
                'technicianId' => $this->technicianId,
                'technicianName' => $this->technicianName,
                'done' => $this->done,
            ],
        ];
    }
}
