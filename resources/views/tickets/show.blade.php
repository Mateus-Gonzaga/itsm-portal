@extends('layouts.app')
@section('title', 'Chamado #'.$ticket->id)
@section('content')
@php use App\Enums\UserRole; use App\Enums\TicketStatus; $u = auth()->user(); @endphp

<a href="{{ route('tickets.index') }}" class="btn btn-sm btn-link text-decoration-none mb-2 px-0">
    <i class="bi bi-arrow-left"></i> Voltar para chamados
</a>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <span class="badge bg-light text-dark border me-1"><i class="bi {{ $ticket->type->icon() }} me-1"></i>{{ $ticket->type->label() }}</span>
                        <span class="badge bg-{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span>
                        @if ($ticket->isOverdue())
                            <span class="badge bg-danger"><i class="bi bi-clock-history me-1"></i>SLA estourado</span>
                        @endif
                    </div>
                    <span class="text-muted small">#{{ $ticket->id }}</span>
                </div>
                <h1 class="h4 mb-3">{{ $ticket->title }}</h1>
                <p class="mb-0" style="white-space: pre-line">{{ $ticket->description }}</p>
            </div>
        </div>

        {{-- Anexos: imagens exibidas inline; demais arquivos como link --}}
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-paperclip me-1"></i> Anexos <span class="text-secondary small fw-normal">({{ $attachments->count() }})</span></span>
            </div>
            <div class="card-body">
                @if ($attachments->isEmpty())
                    <p class="text-muted small mb-3">Nenhum anexo ainda. Envie uma foto ou print do problema: ajuda muito no atendimento.</p>
                @else
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        @foreach ($attachments as $a)
                            @php $url = route('tickets.attachments.show', [$ticket->id, $a['id']]); @endphp
                            @if ($a['isImage'])
                                <a href="{{ $url }}" target="_blank" title="{{ $a['filename'] }}" class="d-block border rounded overflow-hidden" style="width:150px;height:110px">
                                    <img src="{{ $url }}" alt="{{ $a['filename'] }}" loading="lazy"
                                         style="width:100%;height:100%;object-fit:cover">
                                </a>
                            @else
                                <a href="{{ $url }}" target="_blank" class="d-flex align-items-center gap-2 border rounded px-3 py-2 text-decoration-none" style="max-width:230px">
                                    <i class="bi bi-file-earmark-pdf fs-4 text-danger"></i>
                                    <span class="small text-truncate">{{ $a['filename'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('tickets.attachments.store', $ticket->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="file" name="files[]" class="form-control @error('files.*') is-invalid @enderror"
                               accept="image/*,.pdf" multiple required aria-label="Escolher arquivos">
                        <button class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i> Enviar</button>
                        @error('files.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text">Imagens ou PDF, até 8 MB cada (máx. 5).</div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-left-dots me-1"></i> Acompanhamento</div>
            <div class="card-body">
                @forelse ($timeline as $c)
                    @php $doCliente = $ticket->requesterName && mb_strtolower(trim((string) $c->author)) === mb_strtolower(trim((string) $ticket->requesterName)); $daEquipe = ! $doCliente; @endphp
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                  style="width:38px;height:38px;background:#111827;color:#fff;font-size:1rem" title="{{ $c->author }}"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
                        </div>
                        <div class="ms-3 flex-grow-1 min-w-0 tl-item {{ $daEquipe ? 'equipe' : 'cliente' }}">
                            <div class="small mb-1">
                                <strong>{{ $c->author }}</strong>
                                <span class="badge {{ $doCliente ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }} ms-1">{{ $doCliente ? 'Cliente' : $c->authorRole }}</span>
                                <span class="text-muted ms-1">{{ $c->createdAt->format('d/m/Y H:i') }}</span>
                            </div>
                            <div style="white-space:pre-line">{{ $c->content }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Nenhum acompanhamento ainda. Seja o primeiro a comentar.</p>
                @endforelse

                <form class="mt-3" method="POST" action="{{ route('tickets.comments.store', $ticket->id) }}">
                    @csrf
                    <div class="mb-2">
                        <textarea name="content" rows="3" class="form-control @error('content') is-invalid @enderror"
                                  placeholder="Escreva um comentário..." required>{{ old('content') }}</textarea>
                        @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="small text-secondary d-none d-sm-inline"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> envia</span>
                        <button class="btn btn-primary btn-sm ms-auto"><i class="bi bi-send me-1"></i> Comentar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ticket-side">
        {{-- Cliente: chamado resolvido aguardando aprovação (primeiro, é a ação principal dele) --}}
        @if ($u->role === UserRole::Cliente && $ticket->status === TicketStatus::Solved)
            <div class="card border-0 shadow-sm mb-4 ticket-approve">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="ticket-approve-ic"><i class="bi bi-check2-circle"></i></span>
                        <div>
                            <div class="fw-semibold">Este chamado foi resolvido</div>
                            <div class="small text-secondary">O problema foi solucionado? Aprove para encerrar, ou reabra se ainda precisar de ajuda.</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('tickets.approve', $ticket->id) }}" class="flex-fill">
                            @csrf
                            <button class="btn btn-success w-100"><i class="bi bi-check2-circle me-1"></i> Aprovar solução</button>
                        </form>
                        <form method="POST" action="{{ route('tickets.reopen', $ticket->id) }}" class="flex-fill">
                            @csrf
                            <button class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-counterclockwise me-1"></i> Reabrir</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- Detalhes: primeiro, para a informação ficar à vista --}}
        @php
            $clienteFull = html_entity_decode((string) ($clienteEntity ?? $ticket->entity), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $clienteShort = trim((string) \Illuminate\Support\Arr::last(preg_split('/\s*[>›]\s*/u', $clienteFull) ?: [$clienteFull]));
        @endphp
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1 text-success"></i> Detalhes</div>
            <div class="card-body">
                <dl class="ticket-dl mb-0">
                    <dt>Solicitante</dt><dd>{{ $ticket->requesterName }}</dd>
                    <dt>Cliente</dt><dd title="{{ $clienteFull }}">{{ $clienteShort ?: '—' }}</dd>
                    <dt>Técnico</dt><dd>{!! $ticket->technicianName ? e($ticket->technicianName) : '<span class="text-secondary">Não atribuído</span>' !!}</dd>
                    <dt>Categoria</dt><dd>{{ $ticket->category ?? '—' }}</dd>
                    <dt>Prioridade</dt><dd><span class="badge bg-{{ $ticket->priority->color() }}-subtle text-{{ $ticket->priority->color() }}-emphasis">{{ $ticket->priority->label() }}</span></dd>
                    <dt>Aberto em</dt><dd>{{ $ticket->createdAt->format('d/m/Y H:i') }}</dd>
                    <dt>Prazo (SLA)</dt>
                    <dd>
                        {{ $ticket->dueDate?->format('d/m/Y H:i') ?? '—' }}
                        @if ($ticket->isOverdue())<span class="badge bg-danger ms-1">atrasado</span>@endif
                    </dd>
                    @if (! empty($linkedAssets))
                        <dt>Equipamento</dt>
                        <dd>
                            @foreach ($linkedAssets as $la)
                                <a href="{{ route('modules.inventory', ['q' => $la['name']]) }}" class="d-block text-decoration-none">
                                    <i class="bi bi-pc-display me-1"></i>{{ $la['name'] }} <span class="text-secondary">({{ $la['type'] }})</span>
                                </a>
                            @endforeach
                        </dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Ações do técnico/gestor, agrupadas por assunto --}}
        @if ($u->role === UserRole::Tecnico || $u->role === UserRole::Gestor)
            @php
                $souResponsavel = $ticket->technicianName === $u->name;
                $aberto = ! in_array($ticket->status, [TicketStatus::Solved, TicketStatus::Closed], true);
            @endphp
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-tools me-1 text-success"></i> Atendimento</div>
                <div class="card-body">

                    {{-- 1. Situação: ações rápidas num clique; a observação vai para o acompanhamento --}}
                    <div class="ticket-sec">
                        <div class="ticket-sec-t">Situação</div>
                        <form method="POST" action="{{ route('tickets.status', $ticket->id) }}">
                            @csrf
                            <input type="text" name="note" class="form-control form-control-sm mb-2" maxlength="2000"
                                   placeholder="{{ $aberto ? 'Observação ou solução (vai para o acompanhamento)' : 'Observação (opcional)' }}">
                            @if ($aberto)
                                <div class="ticket-quick">
                                    @if ($ticket->status !== TicketStatus::InProgress)
                                        <button name="status" value="{{ TicketStatus::InProgress->value }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-play-circle me-1"></i>Em andamento</button>
                                    @endif
                                    @if ($ticket->status !== TicketStatus::Pending)
                                        <button name="status" value="{{ TicketStatus::Pending->value }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pause-circle me-1"></i>Pendente</button>
                                    @endif
                                    <button name="status" value="{{ TicketStatus::Solved->value }}" class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>Resolver</button>
                                </div>
                            @endif
                            <details class="ticket-more mt-2" @unless ($aberto) open @endunless>
                                <summary>Outra situação</summary>
                                <div class="input-group input-group-sm mt-2">
                                    <select name="status_outro" class="form-select" onchange="this.form.querySelector('[data-status-outro]').value = this.value">
                                        @foreach (TicketStatus::cases() as $s)
                                            <option value="{{ $s->value }}" @selected($ticket->status === $s)>{{ $s->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button name="status" value="{{ $ticket->status->value }}" data-status-outro class="btn btn-outline-secondary">Aplicar</button>
                                </div>
                            </details>
                        </form>
                    </div>

                    {{-- 2. Responsável --}}
                    <div class="ticket-sec">
                        <div class="ticket-sec-t">Responsável</div>
                        @if ($souResponsavel)
                            <div class="small mb-2"><i class="bi bi-person-check-fill text-success me-1"></i>Você é o responsável por este chamado.</div>
                        @else
                            <form method="POST" action="{{ route('tickets.assign', $ticket->id) }}" class="mb-2">
                                @csrf
                                <button class="btn btn-primary btn-sm w-100"><i class="bi bi-person-check me-1"></i> Assumir chamado</button>
                            </form>
                        @endif
                        @if ($technicians->isNotEmpty())
                            <form method="POST" action="{{ route('tickets.assign', $ticket->id) }}">
                                @csrf
                                <div class="input-group input-group-sm">
                                    <select name="technician_glpi_id" class="form-select" required aria-label="Atribuir a outro técnico"
                                            onchange="this.form.technician_name.value = this.options[this.selectedIndex].text">
                                        <option value="">Atribuir a outro técnico…</option>
                                        @foreach ($technicians as $t)
                                            <option value="{{ $t['id'] }}" @selected($ticket->technicianName === $t['name'])>{{ $t['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="technician_name" value="{{ $ticket->technicianName }}">
                                    <button class="btn btn-outline-secondary">Atribuir</button>
                                </div>
                            </form>
                        @endif
                    </div>

                    {{-- 3. Categoria --}}
                    @if ($categories->isNotEmpty())
                        <div class="ticket-sec">
                            <div class="ticket-sec-t">Categoria</div>
                            <form method="POST" action="{{ route('tickets.category', $ticket->id) }}">
                                @csrf
                                <div class="input-group input-group-sm">
                                    <select name="category_id" class="form-select" required aria-label="Categoria">
                                        <option value="">Escolher categoria…</option>
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat['id'] }}" @selected($ticket->category && ($ticket->category === $cat['completename'] || $ticket->category === $cat['name']))>{{ $cat['completename'] }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-secondary">Salvar</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    {{-- 4. Prazo --}}
                    <div class="ticket-sec">
                        <div class="ticket-sec-t">Prazo (SLA)</div>
                        <form method="POST" action="{{ route('tickets.sla', $ticket->id) }}">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="datetime-local" name="due_date" class="form-control" aria-label="Prazo (SLA)"
                                       value="{{ $ticket->dueDate?->format('Y-m-d\TH:i') }}" required>
                                <button class="btn btn-outline-secondary"><i class="bi bi-clock me-1"></i>Salvar</button>
                            </div>
                        </form>
                    </div>

                    {{-- 5. Cliente --}}
                    @if ($clients->isNotEmpty())
                        <div class="ticket-sec">
                            <div class="ticket-sec-t">Cliente (solicitante)</div>
                            <form method="POST" action="{{ route('tickets.client', $ticket->id) }}">
                                @csrf
                                <div class="input-group input-group-sm">
                                    <select name="requester_glpi_id" class="form-select" required aria-label="Trocar cliente">
                                        <option value="">Trocar cliente…</option>
                                        @foreach ($clients as $c)
                                            <option value="{{ $c['id'] }}" @selected($ticket->requesterName === $c['name'])>{{ $c['name'] }}@if ($c['entity']) · {{ \Illuminate\Support\Arr::last(preg_split('/\s*[>›]\s*/u', html_entity_decode($c['entity'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) }}@endif</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-secondary">Trocar</button>
                                </div>
                                <div class="form-text">Move o chamado para a entidade do cliente escolhido.</div>
                            </form>
                        </div>
                    @endif

                    {{-- Zona de risco: discreta, no fim --}}
                    <form method="POST" action="{{ route('tickets.destroy', $ticket->id) }}" class="text-end pt-2"
                          onsubmit="return confirm('Excluir o chamado #{{ $ticket->id }}? Ele vai para a lixeira do GLPI (reversível).')">
                        @csrf @method('DELETE')
                        <button class="btn btn-link btn-sm text-danger text-decoration-none p-0"><i class="bi bi-trash me-1"></i>Excluir chamado</button>
                    </form>
                </div>
            </div>
        @endif
        </div>
    </div>
</div>

<style>
    .ticket-side { position:sticky; top:calc(var(--topbar-h) + 16px); }
    @media (max-width:991.98px) { .ticket-side { position:static; } }
    .ticket-dl { display:grid; grid-template-columns:110px 1fr; gap:6px 12px; font-size:.875rem; }
    .ticket-dl dt { font-weight:600; color:var(--muted-c); }
    .ticket-dl dd { margin:0; min-width:0; overflow-wrap:anywhere; }
    .ticket-sec { padding-bottom:14px; margin-bottom:14px; border-bottom:1px solid var(--border-c); }
    .ticket-sec-t { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted-c); margin-bottom:8px; }
    .ticket-quick { display:flex; flex-wrap:wrap; gap:6px; }
    .ticket-quick .btn { flex:1 1 auto; }
    .ticket-more summary { font-size:.8rem; color:var(--muted-c); cursor:pointer; list-style:revert; }
    .ticket-more summary:hover { color:var(--fl-green); }
    .ticket-approve { border-left:4px solid var(--fl-green) !important; }
    .ticket-approve-ic { width:38px; height:38px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:rgba(6,138,79,.12); color:var(--fl-green); font-size:1.2rem; flex:0 0 auto; }
    .tl-item { padding:10px 12px; border-radius:12px; border:1px solid var(--border-c); background:var(--surface); }
    .tl-item.equipe { border-left:3px solid var(--fl-green); }
    .tl-item.cliente { border-left:3px solid #3b82f6; }
</style>
<script>
    // Evita envio duplicado (clique duplo) em todas as ações desta tela.
    document.querySelectorAll('.row.g-4 form').forEach(function (f) {
        f.addEventListener('submit', function () {
            setTimeout(function () { f.querySelectorAll('button').forEach(function (b) { b.disabled = true; }); }, 0);
        });
    });
    // Ctrl+Enter envia o comentário.
    var comentario = document.querySelector('textarea[name="content"]');
    if (comentario) comentario.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); comentario.form.requestSubmit(); }
    });
</script>
@endsection
