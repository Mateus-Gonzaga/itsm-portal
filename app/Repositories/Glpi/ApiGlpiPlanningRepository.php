<?php

namespace App\Repositories\Glpi;

use App\Data\PlanningEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Agenda contra a API REST do GLPI 11.
 *
 * Eventos MÓVEIS = TicketTask (campos begin/end/users_id_tech/state). Eventos
 * FIXOS = prazo de SLA do chamado (time_to_resolve). Remarcar = PUT na
 * TicketTask, o que mantém o Planning nativo do GLPI sincronizado de graça.
 *
 * Mantém a própria sessão/cliente/mapa de usuários para não acoplar ao
 * ApiGlpiTicketRepository (cada repositório é autocontido; eventual base comum
 * fica como melhoria futura).
 */
class ApiGlpiPlanningRepository implements GlpiPlanningRepositoryInterface
{
    private ?string $sessionToken = null;

    private ?array $userMap = null;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $appToken,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public function events(array $filters = []): Collection
    {
        $techId = ! empty($filters['technician_glpi_id']) ? (int) $filters['technician_glpi_id'] : null;

        $tickets = $this->ticketsMap();           // id => ['title','due','open']
        $tasks = $this->tasks();                  // TicketTask cruas

        // Tarefas agendadas (begin válido) de chamados ABERTOS -> eventos móveis.
        // Chamado resolvido/fechado sai da agenda (não polui o planejamento).
        $taskEvents = $tasks
            ->filter(fn (array $t) => $this->validDate($t['begin'] ?? null)
                && ($tickets[(int) ($t['tickets_id'] ?? 0)]['open'] ?? false))
            ->map(fn (array $t) => $this->taskEvent($t, $tickets))
            ->filter()
            ->values();

        if ($techId !== null) {
            $taskEvents = $taskEvents->filter(fn (PlanningEvent $e) => $e->technicianId === $techId)->values();
        }

        // Camada de SLA (fixa). Quando filtra por técnico, só dos chamados em que
        // ele tem tarefa — evita poluir a visão pessoal do técnico.
        $slaScope = $techId !== null ? $taskEvents->pluck('ticketId')->unique()->all() : null;
        $slaEvents = collect($tickets)
            ->filter(fn (array $t) => $t['open'] && $this->validDate($t['due']))
            ->filter(fn (array $t, int $id) => $slaScope === null || in_array($id, $slaScope, true))
            ->map(fn (array $t, int $id) => new PlanningEvent(
                id: "sla-{$id}",
                ticketId: $id,
                title: "Prazo: #{$id} {$t['title']}",
                start: $this->date($t['due']),
                end: null,
                type: 'sla',
                movable: false,
            ))
            ->values();

        // Tarefas livres da equipe (PlanningExternalEvent): SEMPRE visíveis
        // (demandas compartilhadas) — não entram no filtro por técnico.
        return $taskEvents->merge($slaEvents)->merge($this->externalEvents())->values();
    }

    public function createEvent(string $title, CarbonImmutable $begin, CarbonImmutable $end, ?int $ownerGlpiId = null, ?string $content = null): void
    {
        $input = [
            'name' => $title,
            'text' => $content !== null && $content !== '' ? $content : $title,
            'begin' => $begin->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'state' => 1, // 1 = a fazer
        ];
        if ($ownerGlpiId !== null && $ownerGlpiId > 0) {
            $input['users_id'] = $ownerGlpiId;
        }

        $this->client()->post('/PlanningExternalEvent', ['input' => $input])->throw();
    }

    public function rescheduleEvent(int $eventId, CarbonImmutable $begin, CarbonImmutable $end): void
    {
        $this->client()->put("/PlanningExternalEvent/{$eventId}", [
            'input' => [
                'begin' => $begin->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ],
        ])->throw();
    }

    public function setEventDone(int $eventId, bool $done): void
    {
        $this->client()->put("/PlanningExternalEvent/{$eventId}", [
            'input' => ['state' => $done ? 2 : 1],
        ])->throw();
    }

    public function deleteEvent(int $eventId): void
    {
        $this->client()->delete("/PlanningExternalEvent/{$eventId}")->throw();
    }

    public function reschedule(int $taskId, CarbonImmutable $begin, CarbonImmutable $end): void
    {
        $this->client()->put("/TicketTask/{$taskId}", [
            'input' => [
                'begin' => $begin->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ],
        ])->throw();
    }

    public function schedule(int $ticketId, int $technicianGlpiId, CarbonImmutable $begin, CarbonImmutable $end, ?string $content = null): void
    {
        $this->client()->post('/TicketTask', [
            'input' => [
                'tickets_id' => $ticketId,
                'users_id_tech' => $technicianGlpiId,
                'begin' => $begin->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
                'state' => 1, // 1 = a fazer
                'content' => $content !== null && $content !== '' ? $content : 'Atendimento agendado.',
            ],
        ])->throw();
    }

    // ----------------------------------------------------------------

    private function taskEvent(array $t, array $tickets): ?PlanningEvent
    {
        $ticketId = (int) ($t['tickets_id'] ?? 0);
        if ($ticketId === 0) {
            return null;
        }

        $techId = (int) ($t['users_id_tech'] ?? 0) ?: null;
        $title = $tickets[$ticketId]['title'] ?? 'Chamado';

        return new PlanningEvent(
            id: 'task-'.(int) $t['id'],
            ticketId: $ticketId,
            title: "#{$ticketId} {$title}",
            start: $this->date($t['begin']),
            end: $this->validDate($t['end'] ?? null) ? $this->date($t['end']) : null,
            type: 'task',
            movable: true,
            technicianName: $techId !== null ? ($this->userMap()[$techId] ?? null) : null,
            technicianId: $techId,
            taskId: (int) $t['id'],
            done: (int) ($t['state'] ?? 0) === 2,
        );
    }

    /**
     * Tarefas livres da equipe (PlanningExternalEvent). Lidas com o token do
     * usuário logado, então o GLPI já aplica o escopo por entidade/perfil.
     *
     * @return Collection<int, PlanningEvent>
     */
    private function externalEvents(): Collection
    {
        $resp = $this->client()->get('/PlanningExternalEvent', ['range' => '0-499']);
        if (! $resp->successful() || ! is_array($resp->json())) {
            return collect();
        }

        return collect($resp->json())
            ->filter(fn (array $e) => $this->validDate($e['begin'] ?? null))
            ->map(function (array $e) {
                $ownerId = ((int) ($e['users_id'] ?? 0)) ?: null;

                return new PlanningEvent(
                    id: 'ext-'.(int) $e['id'],
                    ticketId: 0,
                    title: (string) ($e['name'] ?? 'Tarefa'),
                    start: $this->date($e['begin']),
                    end: $this->validDate($e['end'] ?? null) ? $this->date($e['end']) : null,
                    type: 'event',
                    movable: true,
                    technicianName: $ownerId !== null ? ($this->userMap()[$ownerId] ?? null) : null,
                    technicianId: $ownerId,
                    done: (int) ($e['state'] ?? 0) === 2,
                    eventId: (int) $e['id'],
                );
            })
            ->values();
    }

    /** @return array<int, array{title: string, due: ?string, open: bool}> */
    private function ticketsMap(): array
    {
        $resp = $this->client()->get('/Ticket', ['range' => '0-499']);
        if (! $resp->successful() || ! is_array($resp->json())) {
            return [];
        }

        $map = [];
        foreach ($resp->json() as $t) {
            $status = (int) ($t['status'] ?? 1);
            $map[(int) $t['id']] = [
                'title' => (string) ($t['name'] ?? 'Chamado'),
                'due' => $t['time_to_resolve'] ?? null,
                'open' => ! in_array($status, [5, 6], true), // 5=resolvido, 6=fechado
            ];
        }

        return $map;
    }

    /** @return Collection<int, array> TicketTask cruas (ids, sem expand). */
    private function tasks(): Collection
    {
        $resp = $this->client()->get('/TicketTask', ['range' => '0-499']);

        return $resp->successful() && is_array($resp->json()) ? collect($resp->json()) : collect();
    }

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

    private function validDate(mixed $d): bool
    {
        return is_string($d) && $d !== '' && ! str_starts_with($d, '0000-00-00') && strtolower($d) !== 'null';
    }

    private function date(?string $d): CarbonImmutable
    {
        return CarbonImmutable::parse($d);
    }
}
