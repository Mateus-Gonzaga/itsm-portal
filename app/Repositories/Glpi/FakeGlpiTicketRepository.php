<?php

namespace App\Repositories\Glpi;

use App\Data\TicketComment;
use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Implementação de MENTIRA (Fase 1): dados mock, sem tocar no GLPI.
 *
 * Para a demo funcionar de verdade entre requisições, guardamos na sessão:
 *  - chamados abertos (TICKETS_KEY)
 *  - comentários da timeline (TIMELINE_KEY.{id})
 *  - alterações (status/técnico) sobre qualquer chamado (OVERRIDES_KEY.{id})
 * Na Fase 2, o ApiGlpiTicketRepository fará tudo isso contra o GLPI.
 */
class FakeGlpiTicketRepository implements GlpiTicketRepositoryInterface
{
    private const TICKETS_KEY = 'fake_glpi_tickets';
    private const TIMELINE_KEY = 'fake_glpi_timeline';
    private const OVERRIDES_KEY = 'fake_glpi_overrides';

    public function all(array $filters = []): Collection
    {
        $overrides = cache()->get(self::OVERRIDES_KEY, []);

        $tickets = $this->created()->merge($this->seed())
            ->map(fn (TicketData $t) => $overrides[$t->id] ?? $t);

        if (! empty($filters['status'])) {
            $tickets = $tickets->filter(fn (TicketData $t) => $t->status->value === $filters['status']);
        }
        if (! empty($filters['requester'])) {
            $tickets = $tickets->filter(fn (TicketData $t) => $t->requesterName === $filters['requester']);
        }
        if (! empty($filters['technician'])) {
            $tickets = $tickets->filter(fn (TicketData $t) => $t->technicianName === $filters['technician']);
        }
        if (! empty($filters['entity'])) {
            $tickets = $tickets->filter(fn (TicketData $t) => $t->entity === $filters['entity']);
        }

        return $tickets->values();
    }

    public function find(int|string $id): ?TicketData
    {
        return $this->all()->firstWhere('id', $id);
    }

    public function create(array $attributes): TicketData
    {
        $priority = TicketPriority::tryFrom($attributes['priority'] ?? '') ?? TicketPriority::Medium;

        $ticket = new TicketData(
            id: random_int(1000, 9999),
            title: $attributes['title'] ?? 'Sem título',
            description: $attributes['description'] ?? '',
            status: TicketStatus::New,
            priority: $priority,
            type: TicketType::tryFrom($attributes['type'] ?? '') ?? TicketType::Incident,
            requesterName: $attributes['requester'] ?? 'Cliente Demo',
            entity: $attributes['entity'] ?? 'Minha Empresa',
            createdAt: CarbonImmutable::now(),
            category: $attributes['category'] ?? null,
            dueDate: ! empty($attributes['due_date'])
                ? CarbonImmutable::parse($attributes['due_date'])
                : CarbonImmutable::now()->addHours($this->slaHours($priority)),
            updatedAt: CarbonImmutable::now(),
        );

        cache()->forever(self::TICKETS_KEY, $this->created()->push($ticket)->all());

        return $ticket;
    }

    public function update(int|string $id, array $attributes): TicketData
    {
        $t = $this->find($id);

        if ($t === null) {
            throw new RuntimeException("Chamado {$id} não encontrado (mock).");
        }

        $updated = new TicketData(
            id: $t->id,
            title: $attributes['title'] ?? $t->title,
            description: $attributes['description'] ?? $t->description,
            status: TicketStatus::tryFrom($attributes['status'] ?? '') ?? $t->status,
            priority: TicketPriority::tryFrom($attributes['priority'] ?? '') ?? $t->priority,
            type: TicketType::tryFrom($attributes['type'] ?? '') ?? $t->type,
            requesterName: $t->requesterName,
            entity: $t->entity,
            createdAt: $t->createdAt,
            technicianName: $attributes['technician'] ?? $t->technicianName,
            category: $attributes['category'] ?? $t->category,
            dueDate: ! empty($attributes['due_date'])
                ? CarbonImmutable::parse($attributes['due_date'])
                : $t->dueDate,
            updatedAt: CarbonImmutable::now(),
        );

        $overrides = cache()->get(self::OVERRIDES_KEY, []);
        $overrides[$id] = $updated;
        cache()->forever(self::OVERRIDES_KEY, $overrides);

        return $updated;
    }

    public function timeline(int|string $id): Collection
    {
        $seeded = $this->seedComments()[$id] ?? [];
        $added = cache()->get(self::TIMELINE_KEY.'.'.$id, []);

        return collect([...$seeded, ...$added])
            ->sortBy(fn (TicketComment $c) => $c->createdAt->getTimestamp())
            ->values();
    }

    public function addFollowup(int|string $id, string $content): void
    {
        $user = auth()->user();

        $comment = new TicketComment(
            author: $user?->name ?? 'Usuário',
            authorRole: $user?->role->label() ?? '—',
            content: $content,
            createdAt: CarbonImmutable::now(),
        );

        $key = self::TIMELINE_KEY.'.'.$id;
        cache()->forever($key, [...cache()->get($key, []), $comment]);
    }

    /** Prazo de SLA (horas) por prioridade — só ilustrativo no mock. */
    private function slaHours(TicketPriority $priority): int
    {
        return match ($priority) {
            TicketPriority::Urgent => 4,
            TicketPriority::High => 8,
            TicketPriority::Medium => 24,
            TicketPriority::Low => 72,
        };
    }

    /** @return Collection<int, TicketData> */
    private function created(): Collection
    {
        return collect(cache()->get(self::TICKETS_KEY, []));
    }

    /** @return Collection<int, TicketData> */
    private function seed(): Collection
    {
        $now = CarbonImmutable::now();

        return collect([
            new TicketData(id: 1, title: 'Impressora não imprime', description: 'A impressora do setor fiscal não responde.', status: TicketStatus::New, priority: TicketPriority::High, type: TicketType::Incident, requesterName: 'Ana Cliente', entity: 'Drogaria Centro', createdAt: $now->subDay(), category: 'Hardware', dueDate: $now->subDay()->addHours(8)),
            new TicketData(id: 2, title: 'Sistema ERP lento', description: 'O ERP trava ao emitir nota fiscal.', status: TicketStatus::InProgress, priority: TicketPriority::Urgent, type: TicketType::Incident, requesterName: 'Ana Cliente', entity: 'Drogaria Centro', createdAt: $now->subDays(2), technicianName: 'Tiago Técnico', category: 'Sistemas', dueDate: $now->subDays(2)->addHours(4)),
            new TicketData(id: 3, title: 'Solicitar acesso ao e-mail', description: 'Novo colaborador precisa de conta de e-mail.', status: TicketStatus::Pending, priority: TicketPriority::Medium, type: TicketType::Request, requesterName: 'Bruno Cliente', entity: 'Drogaria Sul', createdAt: $now->subDays(3), technicianName: 'Tiago Técnico', category: 'Acessos', dueDate: $now->addDay()),
            new TicketData(id: 4, title: 'Troca de monitor', description: 'Monitor com falha de imagem intermitente.', status: TicketStatus::Solved, priority: TicketPriority::Low, type: TicketType::Request, requesterName: 'Bruno Cliente', entity: 'Drogaria Sul', createdAt: $now->subDays(5), technicianName: 'Tiago Técnico', category: 'Hardware', dueDate: $now->subDays(2)),
            new TicketData(id: 5, title: 'VPN não conecta', description: 'Erro de autenticação ao conectar na VPN.', status: TicketStatus::Assigned, priority: TicketPriority::High, type: TicketType::Incident, requesterName: 'Ana Cliente', entity: 'Drogaria Centro', createdAt: $now->subHours(6), technicianName: 'Tiago Técnico', category: 'Redes', dueDate: $now->addHours(2)),
        ]);
    }

    /** @return array<int, array<int, TicketComment>> */
    private function seedComments(): array
    {
        $now = CarbonImmutable::now();

        return [
            2 => [
                new TicketComment('Ana Cliente', 'Cliente', 'O sistema travou de novo ao emitir a nota agora há pouco.', $now->subDays(2)->addHour()),
                new TicketComment('Tiago Técnico', 'Técnico', 'Estou analisando os logs do ERP, retorno ainda hoje.', $now->subDay()),
            ],
            4 => [
                new TicketComment('Tiago Técnico', 'Técnico', 'Monitor substituído. Pode validar e aprovar, por favor?', $now->subDays(2)),
            ],
            5 => [
                new TicketComment('Tiago Técnico', 'Técnico', 'Chamado encaminhado à equipe de redes.', $now->subHours(5)),
            ],
        ];
    }
}
