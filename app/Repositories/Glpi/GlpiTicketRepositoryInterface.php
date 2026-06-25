<?php

namespace App\Repositories\Glpi;

use App\Data\TicketComment;
use App\Data\TicketData;
use Illuminate\Support\Collection;

/**
 * Contrato de acesso aos chamados.
 *
 * Todo o app (controllers, views) depende SÓ desta interface — nunca de uma
 * implementação concreta. O RepositoryServiceProvider decide, pela flag
 * GLPI_DRIVER do .env, se entrega o Fake (mock) ou o Api (GLPI real).
 */
interface GlpiTicketRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TicketData>
     */
    public function all(array $filters = []): Collection;

    public function find(int|string $id): ?TicketData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TicketData;

    /** @param array<string, mixed> $attributes */
    public function update(int|string $id, array $attributes): TicketData;

    /**
     * Linha do tempo do chamado (acompanhamentos/followups).
     *
     * @return Collection<int, TicketComment>
     */
    public function timeline(int|string $id): Collection;

    public function addFollowup(int|string $id, string $content): void;
}
