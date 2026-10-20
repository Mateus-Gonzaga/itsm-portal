@extends('layouts.app')
@section('title', $heading)
@section('content')
@php use App\Enums\UserRole; @endphp

<x-page-header icon="bi-card-list" :title="$heading"
    :subtitle="$onlyMine ? 'Chamados em que você é o técnico responsável.' : 'Acompanhe, filtre e abra chamados.'">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @if ($canFilterMine)
            {{-- Equipe: alterna entre todos os chamados e só os atribuídos a quem está logado --}}
            <div class="btn-group mine-toggle" role="group" aria-label="Quais chamados mostrar">
                <a href="{{ request()->fullUrlWithQuery(['meus' => 0, 'page' => 1]) }}"
                   class="btn {{ $onlyMine ? 'btn-outline-primary' : 'btn-primary' }}" @if (! $onlyMine) aria-current="true" @endif>
                    <i class="bi bi-people me-1"></i> Todos
                </a>
                <a href="{{ request()->fullUrlWithQuery(['meus' => 1, 'page' => 1]) }}"
                   class="btn {{ $onlyMine ? 'btn-primary' : 'btn-outline-primary' }}" @if ($onlyMine) aria-current="true" @endif>
                    <i class="bi bi-person-check me-1"></i> Atribuídos a mim
                    @if ($mineCount !== null)<span class="badge rounded-pill {{ $onlyMine ? 'bg-white text-success' : 'bg-success-subtle text-success-emphasis' }} ms-1">{{ $mineCount }}</span>@endif
                </a>
            </div>
        @endif
        <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-cta">
            <i class="bi bi-plus-circle me-1"></i> Abrir chamado
        </a>
    </div>
</x-page-header>

<div class="card">
    <div class="card-body">
        {{-- Busca + filtros rápidos --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap" style="flex: 1 1 340px">
                <form method="GET" class="d-flex gap-2" style="max-width: 360px; flex: 1 1 250px">
                    @if ($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
                    @if ($perPage !== 10)<input type="hidden" name="per_page" value="{{ $perPage }}">@endif
                    @if ($onlyMine)<input type="hidden" name="meus" value="1">@endif
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por título, entidade ou nº...">
                    </div>
                    <button class="btn btn-sm btn-outline-secondary">Buscar</button>
                </form>

                <div class="d-flex align-items-center gap-1 text-muted small">
                    <span class="text-nowrap"><i class="bi bi-list-ul me-1"></i>Exibir:</span>
                    <select class="form-select form-select-sm" style="width: auto;" onchange="location.href=this.value" title="Quantidade de chamados por página">
                        @foreach ($perPageOptions as $size)
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}"
                                {{ $perPage === $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ request()->fullUrlWithoutQuery(['status', 'page']) }}" class="filter-chip {{ $currentStatus === '' ? 'active' : '' }}">Ativos</a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'all', 'page' => 1]) }}" class="filter-chip {{ $currentStatus === 'all' ? 'active' : '' }}">Todos</a>
                @foreach ($statuses as $s)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $s->value, 'page' => 1]) }}"
                       class="filter-chip {{ $currentStatus === $s->value ? 'active' : '' }}">{{ $s->label() }}</a>
                @endforeach
            </div>
        </div>

        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0 table-cards">
                <thead>
                    <tr>
                        <th>#</th><th>Título</th><th>Cliente</th><th>Técnico</th>
                        <th>Prioridade</th><th>Status</th><th>Aberto</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td class="text-muted">#{{ $ticket->id }}</td>
                            <td>
                                <i class="bi {{ $ticket->type->icon() }} text-muted me-1" title="{{ $ticket->type->label() }}"></i>
                                {{ $ticket->title }}
                                @if ($ticket->isOverdue())<span class="badge bg-danger ms-1">atrasado</span>@endif
                            </td>
                            @php
                                // Só o último nível (ex.: "FL 08 - Guará 1"); o caminho completo fica no tooltip e no chamado.
                                $entFull = (string) ($reqEntities[$ticket->requesterGlpiId] ?? $ticket->entity);
                                $entShort = trim((string) \Illuminate\Support\Arr::last(preg_split('/\s*[>›]\s*/u', $entFull) ?: [$entFull]));
                            @endphp
                            <td class="text-nowrap" title="{{ html_entity_decode($entFull, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}">{{ html_entity_decode($entShort ?: '—', ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</td>
                            <td>{{ $ticket->technicianName ?? '—' }}</td>
                            <td><span class="badge bg-{{ $ticket->priority->color() }}-subtle text-{{ $ticket->priority->color() }}-emphasis">{{ $ticket->priority->label() }}</span></td>
                            <td><span class="badge bg-{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span></td>
                            <td class="text-muted small">{{ $ticket->createdAt->format('d/m/Y') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('tickets.show', $ticket->id) }}" class="btn btn-sm btn-outline-secondary">Abrir</a>
                                @if (auth()->user()->role->value !== 'cliente')
                                    <form method="POST" action="{{ route('tickets.destroy', $ticket->id) }}" class="d-inline"
                                          onsubmit="return confirm('Excluir o chamado #{{ $ticket->id }}? Ele vai para a lixeira do GLPI (reversível).')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Excluir chamado"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0 border-0">
                            @if (filled($q) || ! in_array((string) $currentStatus, ['', 'all'], true))
                                <x-empty-state icon="bi-search" title="Nenhum chamado encontrado"
                                    text="Nada corresponde à busca ou ao filtro de situação. Tente outro termo ou veja todos.">
                                    <a href="{{ route('tickets.index', $onlyMine ? ['meus' => 1] : []) }}" class="btn btn-sm btn-outline-secondary">Limpar filtros</a>
                                </x-empty-state>
                            @elseif ($onlyMine)
                                <x-empty-state icon="bi-person-check" title="Nenhum chamado atribuído a você"
                                    text="Quando você assumir um chamado ou alguém atribuir a você, ele aparece aqui." />
                            @else
                                <x-empty-state icon="bi-inbox" title="Nenhum chamado por aqui"
                                    text="Os chamados abertos aparecem nesta lista, com situação, prioridade e técnico.">
                                    <a href="{{ route('tickets.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i> Abrir chamado</a>
                                </x-empty-state>
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3 pt-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2 text-muted small">
                <span>
                    @if ($tickets->total() > 0)
                        Mostrando <strong>{{ $tickets->firstItem() }}</strong> a <strong>{{ $tickets->lastItem() }}</strong> de <strong>{{ $tickets->total() }}</strong> chamados
                    @else
                        Nenhum chamado encontrado
                    @endif
                </span>
                <span class="mx-1 text-secondary">•</span>
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Exibir:</span>
                    <select class="form-select form-select-sm py-0 ps-2 pe-4" style="height: 30px; width: auto;" onchange="location.href=this.value" title="Quantidade de chamados por página">
                        @foreach ($perPageOptions as $size)
                            <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}"
                                {{ $perPage === $size ? 'selected' : '' }}>
                                {{ $size }} por pág.
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($tickets->hasPages())
                <div class="tickets-pagination">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .tickets-pagination nav > div.d-none.d-sm-flex > div:first-child { display: none !important; }
    .tickets-pagination .pagination { margin-bottom: 0; }
</style>
@endsection
