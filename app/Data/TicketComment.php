<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * Um item da timeline do chamado (acompanhamento/followup).
 *
 * Formato neutro: o Fake guarda em sessão e o ApiGlpiTicketRepository (Fase 2)
 * vai mapear de/para ITILFollowup do GLPI.
 */
final class TicketComment
{
    public function __construct(
        public readonly string $author,
        public readonly string $authorRole,
        public readonly string $content,
        public readonly CarbonImmutable $createdAt,
        public readonly bool $internal = false,
    ) {
    }
}
