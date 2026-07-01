<?php

namespace App\Services;

use App\Data\TicketData;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Notificações do sino, DERIVADAS do GLPI (fonte da verdade) — não há tabela de
 * notificações. Comparamos a atividade dos chamados que o usuário já pode ver
 * (isolamento por entidade preservado) contra o carimbo "visto em" dele.
 *
 * - Cliente: avisado quando um chamado SEU é atualizado (resposta/mudança).
 * - Técnico/Gestor: avisado quando um NOVO chamado é aberto (no escopo dele).
 */
class NotificationService
{
    /** Máximo de itens listados no dropdown. */
    private const MAX_ITEMS = 12;

    public function __construct(
        private readonly GlpiTicketRepositoryInterface $tickets,
    ) {
    }

    /**
     * @return array{count:int, items:array<int, array{id:int|string,title:string,detail:string,when:string,url:string,icon:string}>}
     */
    public function for(User $user): array
    {
        // Sem vínculo com o GLPI não há o que notificar.
        if (empty($user->glpi_id)) {
            return ['count' => 0, 'items' => []];
        }

        // Null (nunca leu) = janela recente, para não inundar no primeiro uso.
        $seen = $user->notifications_seen_at
            ? CarbonImmutable::parse($user->notifications_seen_at)
            : CarbonImmutable::now()->subDay();

        $items = $user->role === UserRole::Cliente
            ? $this->clientItems($user, $seen)
            : $this->staffItems($seen);

        $items = $items
            ->sortByDesc('at')
            ->take(self::MAX_ITEMS)
            ->map(fn (array $i) => [
                'id' => $i['id'],
                'title' => $i['title'],
                'detail' => $i['detail'],
                'when' => $i['at']->locale('pt_BR')->diffForHumans(),
                'url' => route('tickets.show', $i['id']),
                'icon' => $i['icon'],
            ])
            ->values()
            ->all();

        return ['count' => count($items), 'items' => $items];
    }

    /** Marca tudo como lido (zera o sino). */
    public function markRead(User $user): void
    {
        $user->forceFill(['notifications_seen_at' => CarbonImmutable::now()])->save();
    }

    /** Cliente: chamados dele atualizados após "visto em" (houve resposta/mudança). */
    private function clientItems(User $user, CarbonImmutable $seen): Collection
    {
        return $this->tickets->all([
            'requester' => $user->name,
            'requester_glpi_id' => $user->glpi_id,
        ])->filter(function (TicketData $t) use ($seen) {
            $up = $t->updatedAt;

            // Só conta quando houve atividade DEPOIS da abertura (evita avisar
            // o cliente sobre o próprio chamado recém-criado).
            return $up !== null && $up->gt($seen) && $up->gt($t->createdAt->addMinute());
        })->map(fn (TicketData $t) => [
            'id' => $t->id,
            'title' => "Chamado #{$t->id} atualizado",
            'detail' => $t->title,
            'at' => $t->updatedAt,
            'icon' => 'bi-chat-left-text',
        ]);
    }

    /** Técnico/Gestor: chamados abertos após "visto em" (no escopo do usuário). */
    private function staffItems(CarbonImmutable $seen): Collection
    {
        return $this->tickets->all()
            ->filter(fn (TicketData $t) => $t->createdAt->gt($seen))
            ->map(fn (TicketData $t) => [
                'id' => $t->id,
                'title' => "Novo chamado #{$t->id}",
                'detail' => $t->requesterName !== '—'
                    ? "{$t->title} — {$t->requesterName}"
                    : $t->title,
                'at' => $t->createdAt,
                'icon' => 'bi-ticket-detailed',
            ]);
    }
}
