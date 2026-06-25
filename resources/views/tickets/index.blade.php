@extends('layouts.app')
@section('title', $heading.' — FOURLINE')
@section('content')
@php use App\Enums\UserRole; @endphp

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">{{ $heading }}</h1>
    @if (in_array(auth()->user()->role, [UserRole::Cliente, UserRole::Tecnico], true))
        <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-cta">
            <i class="bi bi-plus-circle me-1"></i> Abrir chamado
        </a>
    @endif
</div>

<div class="card">
    <div class="card-body">
        {{-- Busca + filtros rápidos --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <form method="GET" class="d-flex gap-2" style="max-width: 380px; flex: 1 1 280px">
                @if ($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por título ou nº...">
                </div>
                <button class="btn btn-sm btn-outline-secondary">Buscar</button>
            </form>
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
            <table class="table table-hover align-middle mb-0">
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
                            <td>{{ $ticket->entity }}</td>
                            <td>{{ $ticket->technicianName ?? '—' }}</td>
                            <td><span class="badge bg-{{ $ticket->priority->color() }}-subtle text-{{ $ticket->priority->color() }}-emphasis">{{ $ticket->priority->label() }}</span></td>
                            <td><span class="badge bg-{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span></td>
                            <td class="text-muted small">{{ $ticket->createdAt->format('d/m/Y') }}</td>
                            <td><a href="{{ route('tickets.show', $ticket->id) }}" class="btn btn-sm btn-outline-secondary">Abrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum chamado encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tickets->hasPages())
            <div class="mt-3 d-flex justify-content-end">{{ $tickets->links() }}</div>
        @endif
    </div>
</div>
@endsection
