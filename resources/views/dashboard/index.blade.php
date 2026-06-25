@extends('layouts.app')
@section('title', 'Painel — FOURLINE')
@section('content')
@php use App\Enums\UserRole; @endphp

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">Olá, {{ auth()->user()->name }} 👋</h1>
        <p class="text-muted mb-0">{{ $subtitle }}</p>
    </div>
    @if (in_array($role, [UserRole::Cliente, UserRole::Tecnico], true))
        <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-cta">
            <i class="bi bi-plus-circle me-1"></i> Abrir chamado
        </a>
    @endif
</div>

<div class="row g-4 mb-4">
    @php
        $cards = [
            ['Total', $metrics['total'], 'bi-inboxes', ''],
            ['Em aberto', $metrics['open'], 'bi-folder2-open', 'text-primary'],
            ['Atrasados (SLA)', $metrics['overdue'], 'bi-clock-history', 'text-danger'],
            ['Resolvidos', $metrics['solved'], 'bi-check2-circle', 'text-success'],
        ];
    @endphp
    @foreach ($cards as [$label, $value, $icon, $tone])
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi {{ $icon }} fs-3 {{ $tone ?: 'text-muted' }}"></i>
                    <div>
                        <div class="text-muted small text-uppercase">{{ $label }}</div>
                        <div class="h3 mb-0 {{ $tone }}">{{ $value }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="{{ $role === UserRole::Gestor ? 'col-lg-8' : 'col-12' }}">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Chamados recentes</span>
                <a href="{{ route('tickets.index') }}" class="small text-decoration-none">ver todos</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($tickets->take(6) as $ticket)
                    <a href="{{ route('tickets.show', $ticket->id) }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="background: transparent">
                        <span>
                            <i class="bi {{ $ticket->type->icon() }} text-muted me-1"></i>
                            <span class="text-muted me-1">#{{ $ticket->id }}</span>{{ $ticket->title }}
                            @if ($ticket->isOverdue())<span class="badge bg-danger ms-1">atrasado</span>@endif
                        </span>
                        <span class="text-nowrap">
                            <span class="badge bg-{{ $ticket->priority->color() }}-subtle text-{{ $ticket->priority->color() }}-emphasis">{{ $ticket->priority->label() }}</span>
                            <span class="badge bg-{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span>
                        </span>
                    </a>
                @empty
                    <div class="list-group-item text-muted" style="background: transparent">Nenhum chamado por aqui.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Gestor: visão mais completa --}}
    @if ($role === UserRole::Gestor)
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="text-muted small text-uppercase mb-1">Taxa de resolução</div>
                    <div class="display-6 fw-bold text-success mb-0">{{ $metrics['resolution'] }}%</div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Chamados por status</div>
                <div class="card-body">
                    @php $byStatus = $tickets->groupBy(fn ($t) => $t->status->label()); @endphp
                    @forelse ($byStatus as $label => $group)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="badge bg-{{ $group->first()->status->color() }}">{{ $group->count() }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sem dados.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
