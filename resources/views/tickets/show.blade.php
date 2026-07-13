@extends('layouts.app')
@section('title', 'Chamado #'.$ticket->id.' — FOURLINE')
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
                    <p class="text-muted small mb-3">Nenhum anexo ainda. Envie uma imagem do problema — ajuda muito no atendimento.</p>
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
                <form method="POST" action="{{ route('tickets.attachments.store', $ticket->id) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-start flex-wrap">
                    @csrf
                    <div>
                        <input type="file" name="files[]" class="form-control form-control-sm @error('files.*') is-invalid @enderror"
                               accept="image/*,.pdf" multiple required>
                        @error('files.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Imagens ou PDF, até 8 MB cada (máx. 5).</div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload me-1"></i> Enviar</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-left-dots me-1"></i> Acompanhamento</div>
            <div class="card-body">
                @forelse ($timeline as $c)
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                  style="width:38px;height:38px;background:var(--fl-green-light);color:var(--fl-green-xdark);font-weight:700">{{ mb_substr($c->author, 0, 1) }}</span>
                        </div>
                        <div class="ms-3">
                            <div class="small mb-1">
                                <strong>{{ $c->author }}</strong>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">{{ $c->authorRole }}</span>
                                <span class="text-muted ms-1">{{ $c->createdAt->format('d/m/Y H:i') }}</span>
                            </div>
                            <div>{{ $c->content }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Nenhum acompanhamento ainda. Seja o primeiro a comentar.</p>
                @endforelse

                <hr>
                <form method="POST" action="{{ route('tickets.comments.store', $ticket->id) }}">
                    @csrf
                    <div class="mb-2">
                        <textarea name="content" rows="3" class="form-control @error('content') is-invalid @enderror"
                                  placeholder="Escreva um comentário..." required>{{ old('content') }}</textarea>
                        @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i> Comentar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- Ações do técnico/gestor --}}
        @if ($u->role === UserRole::Tecnico || $u->role === UserRole::Gestor)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Atendimento</div>
                <div class="card-body">
                    @if ($ticket->technicianName !== $u->name)
                        <form method="POST" action="{{ route('tickets.assign', $ticket->id) }}" class="mb-2">
                            @csrf
                            <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-person-check me-1"></i> Assumir chamado</button>
                        </form>
                    @endif
                    @if ($technicians->isNotEmpty())
                        <form method="POST" action="{{ route('tickets.assign', $ticket->id) }}" class="mb-3">
                            @csrf
                            <label class="form-label small mb-1">Atribuir a</label>
                            <div class="input-group input-group-sm">
                                <select name="technician_glpi_id" class="form-select" required
                                        onchange="this.form.technician_name.value = this.options[this.selectedIndex].text">
                                    <option value="">— escolher técnico —</option>
                                    @foreach ($technicians as $t)
                                        <option value="{{ $t['id'] }}" @selected($ticket->technicianName === $t['name'])>{{ $t['name'] }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="technician_name" value="{{ $ticket->technicianName }}">
                                <button class="btn btn-outline-primary"><i class="bi bi-person-check"></i></button>
                            </div>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('tickets.status', $ticket->id) }}">
                        @csrf
                        <label class="form-label small mb-1">Mudar status</label>
                        <select name="status" class="form-select form-select-sm mb-2">
                            @foreach (TicketStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($ticket->status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="Observação (opcional)">
                        <button class="btn btn-primary btn-sm w-100">Atualizar status</button>
                    </form>

                    <hr class="my-3">
                    <form method="POST" action="{{ route('tickets.sla', $ticket->id) }}">
                        @csrf
                        <label class="form-label small mb-1">Prazo (SLA)</label>
                        <input type="datetime-local" name="due_date" class="form-control form-control-sm mb-2"
                               value="{{ $ticket->dueDate?->format('Y-m-d\TH:i') }}" required>
                        <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-clock me-1"></i> Salvar prazo</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Ações do cliente quando resolvido --}}
        @if ($u->role === UserRole::Cliente && $ticket->status === TicketStatus::Solved)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Este chamado foi resolvido</div>
                <div class="card-body d-flex gap-2">
                    <form method="POST" action="{{ route('tickets.approve', $ticket->id) }}" class="flex-fill">
                        @csrf
                        <button class="btn btn-success btn-sm w-100"><i class="bi bi-check2-circle me-1"></i> Aprovar</button>
                    </form>
                    <form method="POST" action="{{ route('tickets.reopen', $ticket->id) }}" class="flex-fill">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-arrow-counterclockwise me-1"></i> Reabrir</button>
                    </form>
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Detalhes</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Solicitante</dt><dd class="col-7">{{ $ticket->requesterName }}</dd>
                    <dt class="col-5">Cliente</dt><dd class="col-7">{{ $ticket->entity }}</dd>
                    <dt class="col-5">Técnico</dt><dd class="col-7">{{ $ticket->technicianName ?? 'Não atribuído' }}</dd>
                    <dt class="col-5">Categoria</dt><dd class="col-7">{{ $ticket->category ?? '—' }}</dd>
                    <dt class="col-5">Prioridade</dt><dd class="col-7"><span class="badge bg-{{ $ticket->priority->color() }}-subtle text-{{ $ticket->priority->color() }}-emphasis">{{ $ticket->priority->label() }}</span></dd>
                    <dt class="col-5">Aberto em</dt><dd class="col-7">{{ $ticket->createdAt->format('d/m/Y H:i') }}</dd>
                    <dt class="col-5">Prazo (SLA)</dt>
                    <dd class="col-7">
                        {{ $ticket->dueDate?->format('d/m/Y H:i') ?? '—' }}
                        @if ($ticket->isOverdue())<span class="badge bg-danger ms-1">atrasado</span>@endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
