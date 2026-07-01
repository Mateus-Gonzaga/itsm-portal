<?php

namespace App\Data;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Carbon\CarbonImmutable;

/**
 * DTO NEUTRO de um chamado.
 *
 * De propósito não espelha o JSON do GLPI: tanto o FakeGlpiTicketRepository
 * quanto o ApiGlpiTicketRepository devolvem este mesmo formato. Assim,
 * controllers e views nunca dependem do GLPI — trocar o driver fake -> api
 * não muda nada acima desta camada.
 */
final class TicketData
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly TicketStatus $status,
        public readonly TicketPriority $priority,
        public readonly TicketType $type,
        public readonly string $requesterName,
        public readonly string $entity,
        public readonly CarbonImmutable $createdAt,
        public readonly ?string $technicianName = null,
        public readonly ?string $category = null,
        public readonly ?CarbonImmutable $dueDate = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        // ID do solicitante no GLPI — usado no controle de acesso do cliente
        // (comparação por ID é confiável; o nome pode variar realname × login).
        public readonly ?int $requesterGlpiId = null,
    ) {
    }

    /** SLA estourado: tem prazo, já venceu e o chamado não foi resolvido/fechado. */
    public function isOverdue(): bool
    {
        return $this->dueDate !== null
            && $this->dueDate->isPast()
            && ! in_array($this->status, [TicketStatus::Solved, TicketStatus::Closed], true);
    }
}
