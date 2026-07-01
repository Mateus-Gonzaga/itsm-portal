<?php

namespace App\Repositories\Glpi;

use App\Data\PlanningEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Contrato da AGENDA (Fase 1 do módulo de agenda interna).
 *
 * Mesma filosofia do GlpiTicketRepositoryInterface: controllers/views dependem
 * só desta interface. O RepositoryServiceProvider entrega Fake (demo) ou Api
 * (GLPI real) conforme GLPI_DRIVER. No GLPI, eventos móveis = TicketTask e as
 * tarefas livres da equipe = PlanningExternalEvent.
 */
interface GlpiPlanningRepositoryInterface
{
    /**
     * Eventos da agenda (tarefas de chamado + prazos de SLA + tarefas livres).
     *
     * @param  array{technician_glpi_id?: int}  $filters
     * @return Collection<int, PlanningEvent>
     */
    public function events(array $filters = []): Collection;

    /** Remarca uma tarefa (TicketTask) — usado pelo arrastar/editar. */
    public function reschedule(int $taskId, CarbonImmutable $begin, CarbonImmutable $end): void;

    /** Agenda uma nova tarefa (TicketTask) em um chamado. */
    public function schedule(
        int $ticketId,
        int $technicianGlpiId,
        CarbonImmutable $begin,
        CarbonImmutable $end,
        ?string $content = null,
    ): void;

    /**
     * Cria uma TAREFA LIVRE da equipe (PlanningExternalEvent) — demanda sem
     * chamado. $ownerGlpiId é o responsável (opcional).
     */
    public function createEvent(
        string $title,
        CarbonImmutable $begin,
        CarbonImmutable $end,
        ?int $ownerGlpiId = null,
        ?string $content = null,
    ): void;

    /** Remarca uma tarefa livre (arrastar/redimensionar). */
    public function rescheduleEvent(int $eventId, CarbonImmutable $begin, CarbonImmutable $end): void;

    /** Marca/desmarca a tarefa livre como concluída. */
    public function setEventDone(int $eventId, bool $done): void;

    /** Exclui uma tarefa livre. */
    public function deleteEvent(int $eventId): void;
}
