<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
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

    public function index(Request $request): View
    {
        $user = $request->user();
        [$base, $heading] = match ($user->role) {
            UserRole::Cliente => [$this->requesterFilter($user), 'Meus chamados'],
            UserRole::Tecnico => [$this->technicianFilter($user), 'Fila de atendimento'],
            UserRole::Gestor => [[], 'Todos os chamados'],
        };

        return $this->listView($request, $base, $heading);
    }

    /** Chamados abertos pelo próprio usuário (usado pelo técnico). */
    public function mine(Request $request): View
    {
        return $this->listView($request, $this->requesterFilter($request->user()), 'Meus chamados');
    }

    public function show(Request $request, int|string $id, GlpiDirectoryRepositoryInterface $dir): View
    {
        $ticket = $this->tickets->find($id);
        abort_if($ticket === null, 404, 'Chamado não encontrado.');
        $this->denyIfNotOwner($request, $ticket);

        return view('tickets.show', [
            'ticket' => $ticket,
            'timeline' => $this->tickets->timeline($id),
            // Lista de técnicos/gestores para atribuição (só p/ staff).
            'technicians' => $request->user()->role === UserRole::Cliente ? collect() : $this->staffTechnicians($dir),
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

    public function create(): View
    {
        return view('tickets.create', [
            'priorities' => TicketPriority::cases(),
            'types' => TicketType::cases(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'type' => ['required', 'string'],
            'category' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        $ticket = $this->tickets->create([
            ...$data,
            ...$this->requesterFilter($request->user()),
        ]);

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

    private function listView(Request $request, array $base, string $heading): View
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
        ]);
    }
}
