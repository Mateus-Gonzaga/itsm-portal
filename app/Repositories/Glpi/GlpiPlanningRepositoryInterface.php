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
 * (GLPI real) conforme GLPI_DRIVER. No GLPI, eventos móveis = TicketTask.
 */
interface GlpiPlanningRepositoryInterface
{
    /**
     * Eventos da agenda (tarefas agendadas + prazos de SLA).
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
}
