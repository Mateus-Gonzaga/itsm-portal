<?php

namespace App\Repositories\Glpi;

use App\Data\TicketComment;
use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Implementação REAL contra a API REST do GLPI 11 (apirest.php).
 *
 * Autentica via Basic auth (initSession) e reusa o session_token durante a
 * requisição. Mapeia os campos do GLPI para os DTOs neutros (TicketData /
 * TicketComment) — ninguém acima desta classe conhece o formato do GLPI.
 */
class ApiGlpiTicketRepository implements GlpiTicketRepositoryInterface
{
    private ?string $sessionToken = null;

    /** Mapa id->nome de exibição dos usuários do GLPI, carregado sob demanda. */
    private ?array $userMap = null;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $appToken,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public function all(array $filters = []): Collection
    {
        // Filtro por ator (solicitante/técnico) usa a busca do GLPI, que conhece
        // os atores; depois carrega cada chamado já com nomes resolvidos.
        if (! empty($filters['requester_glpi_id'])) {
            $tickets = $this->ticketsByActor(4, (int) $filters['requester_glpi_id']);
        } elseif (! empty($filters['technician_glpi_id'])) {
            $tickets = $this->ticketsByActor(5, (int) $filters['technician_glpi_id']);
        } else {
            $tickets = $this->allTickets();
        }

        if (! empty($filters['status'])) {
            $tickets = $tickets->filter(fn (TicketData $t) => $t->status->value === $filters['status']);
        }
        // Fallback por nome (driver Fake passa nome; mantém compatibilidade).
        if (empty($filters['requester_glpi_id']) && ! empty($filters['requester'])) {
            $tickets = $tickets->filter(fn (TicketData $t) => $t->requesterName === $filters['requester']);
        }
        if (empty($filters['technician_glpi_id']) && ! empty($filters['technician'])) {
            $tickets = $tickets->filter(fn (TicketData $t) => $t->technicianName === $filters['technician']);
        }

        return $tickets->values();
    }

    /** Lista todos os chamados (visão do gestor) via getAllItems. */
    private function allTickets(): Collection
    {
        $resp = $this->client()->get('/Ticket', [
            'expand_dropdowns' => 'true',
            'range' => '0-199',
        ]);

        if (! $resp->successful() || ! is_array($resp->json())) {
            return collect();
        }

        return collect($resp->json())->map(fn (array $t) => $this->toTicketData($t))->values();
    }

    /**
     * Chamados em que o usuário GLPI é o ator do tipo informado
     * (campo 4 = solicitante, 5 = técnico atribuído na busca do GLPI).
     */
    private function ticketsByActor(int $field, int $glpiUserId): Collection
    {
        $resp = $this->client()->get('/search/Ticket', [
            'criteria[0][field]' => $field,
            'criteria[0][searchtype]' => 'equals',
            'criteria[0][value]' => $glpiUserId,
            'forcedisplay[0]' => 2, // 2 = id
            'range' => '0-199',
        ]);

        if (! $resp->successful()) {
            return collect();
        }

        $ids = collect($resp->json('data') ?? [])
            ->map(fn (array $row) => (int) ($row['2'] ?? 0))
            ->filter()
            ->values();

        return $ids->map(fn (int $id) => $this->find($id))->filter()->values();
    }

    public function find(int|string $id): ?TicketData
    {
        $resp = $this->client()->get("/Ticket/{$id}", ['expand_dropdowns' => 'true']);

        if ($resp->status() === 404 || ! $resp->successful()) {
            return null;
        }

        return $this->toTicketData($resp->json(), $this->ticketActors((int) $id));
    }

    public function create(array $attributes): TicketData
    {
        $input = [
            'name' => $attributes['title'] ?? 'Sem título',
            'content' => $attributes['description'] ?? '',
            'type' => ($attributes['type'] ?? 'incident') === 'request' ? 2 : 1,
            'priority' => $this->priorityToGlpi($attributes['priority'] ?? 'medium'),
        ];
        // Define o solicitante como ator do chamado (não é campo simples no GLPI).
        if (! empty($attributes['requester_glpi_id'])) {
            $input['_users_id_requester'] = (int) $attributes['requester_glpi_id'];
        }
        // Prazo de atendimento (SLA) — data-limite gravada direto no chamado.
        if (! empty($attributes['due_date'])) {
            $input['time_to_resolve'] = CarbonImmutable::parse($attributes['due_date'])->format('Y-m-d H:i:s');
        }

        $resp = $this->client()->post('/Ticket', ['input' => $input]);
        $resp->throw();

        $id = $resp->json('id') ?? $resp->json('0.id');

        return $this->find($id) ?? throw new RuntimeException('Chamado criado no GLPI mas não encontrado: '.$id);
    }

    public function update(int|string $id, array $attributes): TicketData
    {
        $input = ['id' => (int) $id];
        if (isset($attributes['title'])) {
            $input['name'] = $attributes['title'];
        }
        if (isset($attributes['description'])) {
            $input['content'] = $attributes['description'];
        }
        if (isset($attributes['status'])) {
            $input['status'] = $this->statusToGlpi($attributes['status']);
        }
        if (isset($attributes['priority'])) {
            $input['priority'] = $this->priorityToGlpi($attributes['priority']);
        }
        // Atribui o técnico como ator (type 2) quando informado.
        if (! empty($attributes['technician_glpi_id'])) {
            $input['_users_id_assign'] = (int) $attributes['technician_glpi_id'];
        }
        if (! empty($attributes['due_date'])) {
            $input['time_to_resolve'] = CarbonImmutable::parse($attributes['due_date'])->format('Y-m-d H:i:s');
        }

        $this->client()->put("/Ticket/{$id}", ['input' => $input])->throw();

        return $this->find($id) ?? throw new RuntimeException("Chamado {$id} não encontrado após update.");
    }

    public function timeline(int|string $id): Collection
    {
        $resp = $this->client()->get("/Ticket/{$id}/ITILFollowup", ['expand_dropdowns' => 'true']);

        if (! $resp->successful() || ! is_array($resp->json())) {
            return collect();
        }

        return collect($resp->json())
            ->map(fn (array $f) => new TicketComment(
                author: (string) ($f['users_id'] ?? 'GLPI'),
                authorRole: 'GLPI',
                content: trim(strip_tags((string) ($f['content'] ?? ''))),
                createdAt: $this->date($f['date'] ?? null),
            ))
            ->sortBy(fn (TicketComment $c) => $c->createdAt->getTimestamp())
            ->values();
    }

    public function addFollowup(int|string $id, string $content): void
    {
        $this->client()->post('/ITILFollowup', [
            'input' => [
                'itemtype' => 'Ticket',
                'items_id' => (int) $id,
                'content' => $content,
            ],
        ])->throw();
    }

    // ----------------------------------------------------------------
    // Infra HTTP / sessão
    // ----------------------------------------------------------------

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl)
            ->acceptJson()
            ->withHeaders(array_filter([
                'Session-Token' => $this->session(),
                'App-Token' => $this->appToken ?: null,
            ]));
    }

    private function session(): string
    {
        // Usuário logado via GLPI: usa o token DELE (isolamento nativo por entidade).
        $userToken = session('glpi_token');
        if (is_string($userToken) && $userToken !== '') {
            return $userToken;
        }

        if ($this->sessionToken !== null) {
            return $this->sessionToken;
        }

        $resp = Http::baseUrl($this->apiUrl)
            ->acceptJson()
            ->withBasicAuth($this->user, $this->password)
            ->withHeaders(array_filter(['App-Token' => $this->appToken ?: null]))
            ->get('/initSession');
        $resp->throw();

        $token = $resp->json('session_token');
        if (! $token) {
            throw new RuntimeException('GLPI initSession não retornou session_token.');
        }

        return $this->sessionToken = $token;
    }

    // ----------------------------------------------------------------
    // Mapeamento GLPI -> DTO neutro
    // ----------------------------------------------------------------

    private function toTicketData(array $t, ?array $actors = null): TicketData
    {
        return new TicketData(
            id: (int) ($t['id'] ?? 0),
            title: (string) ($t['name'] ?? '(sem título)'),
            description: trim(strip_tags((string) ($t['content'] ?? ''))),
            status: $this->mapStatus((int) ($t['status'] ?? 1)),
            priority: $this->mapPriority((int) ($t['priority'] ?? 3)),
            type: ((int) ($t['type'] ?? 1)) === 2 ? TicketType::Request : TicketType::Incident,
            // Solicitante/técnico vêm dos atores (Ticket_User). Quando os atores
            // não foram carregados (ex.: lista do gestor, $actors === null) cai no
            // campo bruto; já com atores carregados, ausência = sem técnico (null).
            requesterName: ($actors['requester'] ?? null)
                ?? $this->dropdownName($t['users_id_recipient'] ?? null) ?? '—',
            entity: $this->entityName($t['entities_id'] ?? null),
            createdAt: $this->date($t['date'] ?? null),
            technicianName: $actors !== null
                ? ($actors['technician'] ?? null)
                : $this->dropdownName($t['users_id_lastupdater'] ?? null),
            category: $this->dropdownName($t['itilcategories_id'] ?? null),
            dueDate: ! empty($t['time_to_resolve']) ? $this->date($t['time_to_resolve']) : null,
            updatedAt: ! empty($t['date_mod']) ? $this->date($t['date_mod']) : null,
            requesterGlpiId: $actors['requester_id'] ?? null,
        );
    }

    /**
     * Resolve os atores de um chamado para nomes de exibição.
     * type 1 = solicitante, 2 = técnico atribuído (3 = observador, ignorado).
     */
    private function ticketActors(int $id): array
    {
        $resp = $this->client()->get("/Ticket/{$id}/Ticket_User");
        if (! $resp->successful() || ! is_array($resp->json())) {
            return [];
        }

        $actors = [];
        foreach ($resp->json() as $link) {
            $uid = (int) ($link['users_id'] ?? 0);
            $type = (int) ($link['type'] ?? 0);

            // O ID do solicitante vem direto do vínculo — capturamos ANTES de
            // resolver o nome, pois o cliente (Self-Service) pode não ter
            // direito de listar /User (userMap vazio). O controle de acesso
            // depende deste ID, então ele não pode ficar de fora.
            if ($type === 1 && $uid > 0) {
                $actors['requester_id'] = $uid;
            }

            $name = $this->userMap()[$uid] ?? null;
            if ($name === null) {
                continue;
            }
            if ($type === 1) {
                $actors['requester'] = $name;
            } elseif ($type === 2) {
                $actors['technician'] = $name;
            }
        }

        return $actors;
    }

    /** id -> nome de exibição (realname, senão login) dos usuários do GLPI. */
    private function userMap(): array
    {
        if ($this->userMap !== null) {
            return $this->userMap;
        }

        $resp = $this->client()->get('/User', ['range' => '0-999']);
        if (! $resp->successful() || ! is_array($resp->json())) {
            return $this->userMap = [];
        }

        $map = [];
        foreach ($resp->json() as $u) {
            $name = trim((string) ($u['realname'] ?? '')) ?: (string) ($u['name'] ?? '');
            if ($name !== '') {
                $map[(int) ($u['id'] ?? 0)] = $name;
            }
        }

        return $this->userMap = $map;
    }

    /** Com expand_dropdowns, FKs viram nomes; 0/"" significam "nenhum". */
    private function dropdownName(mixed $value): ?string
    {
        if ($value === null || $value === 0 || $value === '0' || $value === '') {
            return null;
        }

        // O GLPI devolve nomes com entidades HTML (ex.: "&#62;" = ">"); decodifica.
        return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Nome do cliente a partir do caminho da entidade. O GLPI devolve o caminho
     * completo (ex.: "Entidade raiz > CLIENTES > Drogacei > FL 02"); exibimos só
     * o trecho a partir do cliente (depois de "CLIENTES"). Sem "CLIENTES" no
     * caminho, mostra o último nível.
     */
    private function entityName(mixed $value): string
    {
        $name = $this->dropdownName($value);
        if ($name === null) {
            return '—';
        }

        $parts = array_map('trim', explode('>', $name));
        $idx = array_search('CLIENTES', $parts, true);
        if ($idx !== false && isset($parts[$idx + 1])) {
            return implode(' › ', array_slice($parts, $idx + 1));
        }

        return end($parts) ?: $name;
    }

    private function mapStatus(int $s): TicketStatus
    {
        return match ($s) {
            1 => TicketStatus::New,
            2 => TicketStatus::Assigned,
            3 => TicketStatus::InProgress,
            4 => TicketStatus::Pending,
            5 => TicketStatus::Solved,
            6 => TicketStatus::Closed,
            default => TicketStatus::New,
        };
    }

    private function statusToGlpi(string $value): int
    {
        return match (TicketStatus::tryFrom($value)) {
            TicketStatus::New => 1,
            TicketStatus::Assigned => 2,
            TicketStatus::InProgress => 3,
            TicketStatus::Pending => 4,
            TicketStatus::Solved => 5,
            TicketStatus::Closed => 6,
            default => 1,
        };
    }

    private function mapPriority(int $p): TicketPriority
    {
        return match (true) {
            $p <= 2 => TicketPriority::Low,
            $p === 3 => TicketPriority::Medium,
            $p === 4 => TicketPriority::High,
            default => TicketPriority::Urgent,
        };
    }

    private function priorityToGlpi(string $value): int
    {
        return match (TicketPriority::tryFrom($value)) {
            TicketPriority::Low => 2,
            TicketPriority::Medium => 3,
            TicketPriority::High => 4,
            TicketPriority::Urgent => 5,
            default => 3,
        };
    }

    private function date(?string $d): CarbonImmutable
    {
        if (empty($d) || $d === 'NULL') {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse($d);
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }
}
