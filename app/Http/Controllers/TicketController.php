<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Models\AuditLog;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Enums\UserRole;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    /** Categorias de demonstração (na Fase 2 virão do ITILCategory do GLPI). */
    private const CATEGORIES = ['Hardware', 'Sistemas', 'Acessos', 'Redes', 'Outros'];

    public function __construct(
        private readonly GlpiTicketRepositoryInterface $tickets,
    ) {
    }

    public function index(Request $request, GlpiDirectoryRepositoryInterface $dir): View
    {
        $user = $request->user();
        [$base, $heading] = match ($user->role) {
            UserRole::Cliente => [$this->requesterFilter($user), 'Meus chamados'],
            UserRole::Tecnico => [$this->technicianFilter($user), 'Fila de atendimento'],
            UserRole::Gestor => [[], 'Todos os chamados'],
        };

        return $this->listView($request, $base, $heading, $dir);
    }

    /** Chamados abertos pelo próprio usuário (usado pelo técnico). */
    public function mine(Request $request, GlpiDirectoryRepositoryInterface $dir): View
    {
        return $this->listView($request, $this->requesterFilter($request->user()), 'Meus chamados', $dir);
    }

    /**
     * Mapa glpi_user_id => entidade (loja) do solicitante, com cache curto.
     * A coluna "Cliente" usa isto para refletir a loja do cliente mesmo quando
     * o chamado (por limitação do GLPI) ficou preso em outra entidade.
     *
     * @return array<int, string>
     */
    private function requesterEntityMap(GlpiDirectoryRepositoryInterface $dir): array
    {
        return cache()->remember('tickets_requester_entities', 60, fn () => $dir->users()->pluck('entity', 'id')->all());
    }

    public function show(Request $request, int|string $id, GlpiDirectoryRepositoryInterface $dir): View
    {
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404, 'Chamado não encontrado.');
        $this->denyIfNotOwner($request, $ticket);

        return view('tickets.show', [
            'ticket' => $ticket,
            'timeline' => $this->tickets->timeline($id),
            // Listas para o staff: técnicos (atribuir) e clientes (trocar solicitante).
            'technicians' => $request->user()->role === UserRole::Cliente ? collect() : $this->staffTechnicians($dir),
            'clients' => $request->user()->role === UserRole::Cliente ? collect() : $this->clientUsers($dir),
            'attachments' => $this->tickets->attachments($id),
            // "Cliente" = entidade (loja) do solicitante; cai para a entidade do chamado se não achar.
            'clienteEntity' => $this->requesterEntityMap($dir)[$ticket->requesterGlpiId] ?? $ticket->entity,
        ]);
    }

    /** Troca o cliente (solicitante) do chamado após a abertura — só staff. */
    public function changeClient(Request $request, int|string $id, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        abort_if($this->tickets->find($id) === null, 404);

        $data = $request->validate(['requester_glpi_id' => ['required', 'integer']]);

        $cliente = $dir->users()->firstWhere('id', (int) $data['requester_glpi_id']);
        abort_if($cliente === null, 422, 'Cliente não encontrado.');

        $entityId = (int) ($cliente['entity_id'] ?? 0) ?: null;
        $this->tickets->changeRequester($id, (int) $cliente['id'], $entityId, (string) $cliente['name']);
        $this->tickets->addFollowup($id, 'Cliente do chamado alterado para '.$cliente['name'].' ('.($cliente['entity'] ?? '—').') por '.$request->user()->name.'.');
        AuditLog::record('ticket.client.change', "Trocou cliente do chamado #{$id} para {$cliente['name']} (entidade #{$entityId})");

        // Se o cliente não tem loja (entidade) no GLPI, avisa — o chamado não tem para onde ir.
        if ($entityId === null) {
            return back()->with('error', 'Cliente "'.$cliente['name'].'" não tem loja (entidade) definida no GLPI. Ajuste a entidade do usuário no GLPI para o chamado ir para a loja.');
        }

        // Confirma a entidade resultante (diagnóstico: mostra para onde o chamado foi).
        $depois = $this->tickets->find($id);

        return back()->with('status', 'Cliente atualizado para '.$cliente['name'].'. Entidade do chamado: '.($depois?->entity ?? '—').'.');
    }

    /** Anexa arquivos (imagens/PDF) a um chamado existente. */
    public function storeAttachment(Request $request, int|string $id): RedirectResponse
    {
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404);
        $this->denyIfNotOwner($request, $ticket);

        $request->validate([
            'files' => ['required', 'array', 'max:5'],
            'files.*' => ['file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf', 'max:8192'],
        ]);

        try {
            foreach ($request->file('files', []) as $file) {
                $this->tickets->addAttachment($id, $file->getRealPath(), $file->getClientOriginalName());
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $detail = (string) ($e->response?->json('1') ?? $e->response?->json('0') ?? 'o GLPI recusou o envio');

            return back()->with('error', 'Não foi possível anexar: '.$detail);
        }

        return back()->with('status', 'Anexo(s) enviado(s).');
    }

    /**
     * Serve um anexo INLINE (proxy do GLPI): permite exibir imagens direto na
     * página (o navegador não consegue chamar a API do GLPI com os tokens).
     */
    public function showAttachment(Request $request, int|string $id, int $docId): \Illuminate\Http\Response
    {
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404);
        $this->denyIfNotOwner($request, $ticket);

        // O documento precisa pertencer a ESTE chamado (evita ler doc alheio por id).
        abort_unless(
            $this->tickets->attachments($id)->contains(fn (array $a) => $a['id'] === $docId),
            404, 'Anexo não encontrado neste chamado.',
        );

        $file = $this->tickets->downloadAttachment($docId);

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.addslashes($file['filename']).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Técnicos/gestores do GLPI, para o seletor "Atribuir a". */
    private function staffTechnicians(GlpiDirectoryRepositoryInterface $dir): Collection
    {
        return $dir->users()
            ->filter(fn (array $u) => UserRole::fromGlpiProfile((string) ($u['profile'] ?? ''))->isStaff())
            ->map(fn (array $u) => ['id' => (int) $u['id'], 'name' => $u['name']])
            ->unique('id')->sortBy('name')->values();
    }

    /** Clientes (solicitantes possíveis) para o staff abrir chamado em nome deles. */
    private function clientUsers(GlpiDirectoryRepositoryInterface $dir): Collection
    {
        return $dir->users()
            ->reject(fn (array $u) => UserRole::fromGlpiProfile((string) ($u['profile'] ?? ''))->isStaff())
            ->map(fn (array $u) => [
                'id' => (int) $u['id'],
                'name' => $u['name'],
                'entity' => $u['entity'] ?? null,
            ])
            ->unique('id')->sortBy('name')->values();
    }

    public function create(Request $request, GlpiDirectoryRepositoryInterface $dir): View
    {
        $isStaff = $request->user()->role !== UserRole::Cliente;

        return view('tickets.create', [
            'priorities' => TicketPriority::cases(),
            'types' => TicketType::cases(),
            'categories' => self::CATEGORIES,
            'isStaff' => $isStaff,
            // Só o staff escolhe solicitante/técnico; cliente abre em nome próprio.
            'clients' => $isStaff ? $this->clientUsers($dir) : collect(),
            'technicians' => $isStaff ? $this->staffTechnicians($dir) : collect(),
        ]);
    }

    public function store(Request $request, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'type' => ['required', 'string'],
            'category' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'requester_glpi_id' => ['nullable', 'integer'],
            'requester_name' => ['nullable', 'string', 'max:150'],
            'technician_glpi_id' => ['nullable', 'integer'],
            'technician_name' => ['nullable', 'string', 'max:150'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf', 'max:8192'],
        ]);

        $user = $request->user();
        $isStaff = $user->role !== UserRole::Cliente;

        // Solicitante: staff pode abrir em nome de um cliente; cliente sempre é ele mesmo.
        $requester = ($isStaff && ! empty($data['requester_glpi_id']))
            ? ['requester' => $data['requester_name'] ?: 'Cliente', 'requester_glpi_id' => (int) $data['requester_glpi_id']]
            : $this->requesterFilter($user);

        // Entidade do chamado = entidade do SOLICITANTE (não a de quem abre).
        // Resolvida no servidor pelo diretório do GLPI (não confia no formulário).
        $requesterId = (int) ($requester['requester_glpi_id'] ?? $user->glpi_id);
        $entityId = optional($dir->users()->firstWhere('id', $requesterId))['entity_id'] ?? null;

        // Técnico responsável: só o staff atribui (opcional) já na abertura.
        $technician = ($isStaff && ! empty($data['technician_glpi_id']))
            ? ['technician' => $data['technician_name'] ?: 'Técnico', 'technician_glpi_id' => (int) $data['technician_glpi_id']]
            : [];

        $ticket = $this->tickets->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $data['priority'],
            'type' => $data['type'],
            'category' => $data['category'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'entity_id' => $entityId ? (int) $entityId : null,
            ...$requester,
            ...$technician,
        ]);

        // Anexos enviados junto com a abertura (falha num anexo não perde o chamado).
        foreach ($request->file('files', []) as $file) {
            try {
                $this->tickets->addAttachment($ticket->id, $file->getRealPath(), $file->getClientOriginalName());
            } catch (\Throwable) {
                // segue: o chamado já foi criado
            }
        }

        return redirect()->route('tickets.show', $ticket->id)
            ->with('status', "Chamado \"{$ticket->title}\" aberto com sucesso (#{$ticket->id}).");
    }

    public function addComment(Request $request, int|string $id): RedirectResponse
    {
        $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404);
        $this->denyIfNotOwner($request, $ticket);

        $this->tickets->addFollowup($id, $request->string('content')->value());

        return redirect()->route('tickets.show', $id)->with('status', 'Comentário adicionado.');
    }

    public function assign(Request $request, int|string $id): RedirectResponse
    {
        abort_if($this->tickets->find($id) === null, 404);

        $data = $request->validate([
            'technician_glpi_id' => ['nullable', 'integer'],
            'technician_name' => ['nullable', 'string', 'max:150'],
        ]);

        $user = $request->user();
        // Sem técnico informado = "assumir" (atribui a si mesmo).
        $toId = ! empty($data['technician_glpi_id']) ? (int) $data['technician_glpi_id'] : (int) $user->glpi_id;
        $self = $toId === (int) $user->glpi_id;
        $toName = $self ? $user->name : ($data['technician_name'] ?: 'Técnico');

        $this->tickets->update($id, [
            'technician' => $toName,
            'technician_glpi_id' => $toId,
            'status' => TicketStatus::InProgress->value,
        ]);
        $this->tickets->addFollowup($id, $self
            ? 'Chamado assumido por '.$user->name.'.'
            : 'Chamado atribuído a '.$toName.' por '.$user->name.'.');

        return back()->with('status', $self ? 'Você assumiu o chamado.' : 'Chamado atribuído a '.$toName.'.');
    }

    public function updateSla(Request $request, int|string $id): RedirectResponse
    {
        abort_if($this->tickets->find($id) === null, 404);
        $data = $request->validate(['due_date' => ['required', 'date']]);

        $this->tickets->update($id, ['due_date' => $data['due_date']]);
        $prazo = \Carbon\CarbonImmutable::parse($data['due_date'])->format('d/m/Y H:i');
        $this->tickets->addFollowup($id, 'Prazo (SLA) definido para '.$prazo.'.');

        return back()->with('status', 'Prazo atualizado para '.$prazo.'.');
    }

    public function updateStatus(Request $request, int|string $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_if($this->tickets->find($id) === null, 404);

        $this->tickets->update($id, ['status' => $data['status']]);
        $label = TicketStatus::from($data['status'])->label();
        $this->tickets->addFollowup($id, 'Status alterado para "'.$label.'".'.(! empty($data['note']) ? ' '.$data['note'] : ''));

        return back()->with('status', 'Status atualizado para "'.$label.'".');
    }

    /** Exclui o chamado (move para a lixeira do GLPI) — staff. */
    public function destroy(Request $request, int|string $id): RedirectResponse
    {
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404);

        $this->tickets->delete($id);
        AuditLog::record('ticket.delete', "Excluiu (lixeira) o chamado #{$id} \"{$ticket->title}\"");

        return redirect()->route('tickets.index')
            ->with('status', "Chamado #{$id} excluído (movido para a lixeira do GLPI).");
    }

    public function approve(Request $request, int|string $id): RedirectResponse
    {
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404);
        $this->denyIfNotOwner($request, $ticket);
        $this->tickets->update($id, ['status' => TicketStatus::Closed->value]);
        $this->tickets->addFollowup($id, 'Solução aprovada pelo solicitante. Chamado encerrado.');

        return back()->with('status', 'Chamado encerrado. Obrigado pelo retorno!');
    }

    public function reopen(Request $request, int|string $id): RedirectResponse
    {
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404);
        $this->denyIfNotOwner($request, $ticket);
        $this->tickets->update($id, ['status' => TicketStatus::InProgress->value]);
        $this->tickets->addFollowup($id, 'Chamado reaberto pelo solicitante.');

        return back()->with('status', 'Chamado reaberto.');
    }

    /**
     * Filtro/atributos de solicitante. Inclui o nome (driver Fake) e o
     * glpi_id (driver Api filtra pelos atores do GLPI).
     */
    private function requesterFilter($user): array
    {
        return array_filter([
            'requester' => $user->name,
            'requester_glpi_id' => $user->glpi_id,
        ], fn ($v) => $v !== null);
    }

    private function technicianFilter($user): array
    {
        return array_filter([
            'technician' => $user->name,
            'technician_glpi_id' => $user->glpi_id,
        ], fn ($v) => $v !== null);
    }

    private function denyIfNotOwner(Request $request, TicketData $ticket): void
    {
        $user = $request->user();
        if ($user->role !== UserRole::Cliente) {
            return;
        }

        // O repositório lê o chamado com o TOKEN do próprio usuário no GLPI,
        // então o GLPI já aplicou a visibilidade dele: se find() devolveu o
        // chamado, o cliente tem acesso. Com sessão GLPI ativa, confiamos nisso
        // (era aqui que nascia o falso 403 por divergência de nome/ID).
        if (session('glpi_token')) {
            return;
        }

        // Sem token de usuário (fallback raro pela conta de serviço): garante
        // que o chamado é do próprio solicitante comparando com a lista dele.
        $owns = $this->tickets->all($this->requesterFilter($user))
            ->contains(fn (TicketData $t) => (string) $t->id === (string) $ticket->id);

        abort_unless($owns, 403, 'Você não tem acesso a este chamado.');
    }

    private function listView(Request $request, array $base, string $heading, GlpiDirectoryRepositoryInterface $dir): View
    {
        $filters = $base;
        $status = $request->string('status')->value();
        // 'all' = mostrar tudo (inclui fechados); um status específico filtra por ele.
        if ($status !== '' && $status !== 'all') {
            $filters['status'] = $status;
        }

        $all = $this->tickets->all($filters);

        // Padrão (sem filtro): esconde os chamados FECHADOS, mostrando só os ativos.
        if ($status === '') {
            $all = $all->reject(fn (TicketData $t) => $t->status === TicketStatus::Closed)->values();
        }

        $q = trim((string) $request->string('q'));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $all = $all->filter(fn ($t) => str_contains(mb_strtolower($t->title), $needle)
                || str_contains((string) $t->id, $needle))->values();
        }

        // Mais recente primeiro (ID decrescente: 321 em vez de 123)
        $all = $all->sortByDesc(fn (TicketData $t) => (int) $t->id)->values();

        $perPage = 10;
        $page = max(1, (int) $request->integer('page', 1));
        $tickets = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('tickets.index', [
            'tickets' => $tickets,
            'statuses' => TicketStatus::cases(),
            'currentStatus' => $status,
            'q' => $q,
            'heading' => $heading,
            'reqEntities' => $this->requesterEntityMap($dir),
        ]);
    }
}
