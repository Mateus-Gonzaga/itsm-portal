@extends('layouts.app')
@section('title', 'Painel — FOURLINE')

@push('head')
<style>
    .kpi { border:1px solid var(--bs-border-color); border-radius:16px; padding:1.1rem 1.25rem; display:flex; align-items:center; gap:1rem; height:100%; transition:.12s; background:var(--bs-body-bg); }
    .kpi:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(3,61,34,.08); }
    .kpi .ic { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; color:#fff; flex:0 0 auto; }
    .kpi .v { font-size:1.9rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .kpi .l { font-size:.76rem; text-transform:uppercase; letter-spacing:.03em; color:var(--bs-secondary-color); }
    .ic-total { background:linear-gradient(135deg,#0a9d5a,#067a45); }
    .ic-open  { background:linear-gradient(135deg,#3b82f6,#2563eb); }
    .ic-late  { background:linear-gradient(135deg,#ef4444,#b91c1c); }
    .ic-done  { background:linear-gradient(135deg,#22c55e,#15803d); }

    .ring { --p:0; width:130px; height:130px; border-radius:50%; margin:0 auto;
        background:conic-gradient(#067a45 calc(var(--p)*1%), var(--bs-secondary-bg) 0); display:flex; align-items:center; justify-content:center; }
    .ring > div { width:96px; height:96px; border-radius:50%; background:var(--bs-body-bg); display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .ring .pct { font-size:1.7rem; font-weight:700; font-family:'Rajdhani',sans-serif; }

    .bar-row { display:flex; align-items:center; gap:.65rem; margin-bottom:.55rem; }
    .bar-row .name { width:130px; flex:0 0 auto; font-size:.82rem; }
    .bar-track { flex:1 1 auto; background:var(--bs-secondary-bg); border-radius:999px; height:12px; overflow:hidden; }
    .bar-fill { height:100%; border-radius:999px; background:linear-gradient(90deg,#0a9d5a,#A8CF45); min-width:3px; }
    .bar-row .num { width:34px; text-align:right; font-weight:600; font-size:.82rem; }

    .kb-mini { display:flex; gap:.6rem; }
    .kb-mini .col { flex:1; text-align:center; border:1px solid var(--bs-border-color); border-radius:12px; padding:.7rem .3rem; }
    .kb-mini .n { font-size:1.4rem; font-weight:700; font-family:'Rajdhani',sans-serif; }
    .kb-mini .t { font-size:.72rem; color:var(--bs-secondary-color); }
</style>
@endpush

@section('content')
@php use App\Enums\UserRole; @endphp

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">Olá, {{ auth()->user()->name }} 👋</h1>
        <p class="text-muted mb-0">{{ $subtitle }} · {{ now()->locale('pt_BR')->translatedFormat('l, d \d\e F') }}</p>
    </div>
    <div class="d-flex gap-2">
        @if (in_array($role, [UserRole::Cliente, UserRole::Tecnico], true))
            <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-cta"><i class="bi bi-plus-circle me-1"></i> Abrir chamado</a>
        @endif
        @if ($role === UserRole::Gestor)
            <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary"><i class="bi bi-calendar3 me-1"></i> Agenda</a>
            <a href="{{ route('modules.reports') }}" class="btn btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i> Relatórios</a>
        @endif
    </div>
</div>

{{-- KPIs --}}
<div class="row g-3 mb-4">
    @php
        $cards = [
            ['Total', $metrics['total'], 'bi-inboxes', 'ic-total'],
            ['Em aberto', $metrics['open'], 'bi-folder2-open', 'ic-open'],
            ['Atrasados (SLA)', $metrics['overdue'], 'bi-clock-history', 'ic-late'],
            ['Resolvidos', $metrics['solved'], 'bi-check2-circle', 'ic-done'],
        ];
    @endphp
    @foreach ($cards as [$label, $value, $icon, $tone])
        <div class="col-6 col-lg-3">
            <div class="kpi">
                <span class="ic {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                <div><div class="v">{{ $value }}</div><div class="l">{{ $label }}</div></div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    {{-- Coluna principal --}}
    <div class="{{ $role === UserRole::Gestor ? 'col-lg-8' : 'col-12' }}">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Chamados recentes</span>
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
                    <div class="list-group-item text-muted text-center py-5" style="background: transparent">
                        <i class="bi bi-inbox d-block fs-2 mb-2 opacity-50"></i>
                        Nenhum chamado por aqui ainda.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Chamados por prioridade (gestor/técnico) --}}
        @if ($role !== UserRole::Cliente)
            <div class="card">
                <div class="card-header fw-semibold">Chamados por prioridade</div>
                <div class="card-body">
                    @forelse ($byPriority as $r)
                        <div class="bar-row">
                            <span class="name"><span class="badge bg-{{ $r['color'] }}-subtle text-{{ $r['color'] }}-emphasis">{{ $r['label'] }}</span></span>
                            <span class="bar-track"><span class="bar-fill" style="width: {{ $metrics['total'] ? round($r['count'] / $metrics['total'] * 100) : 0 }}%"></span></span>
                            <span class="num">{{ $r['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sem dados ainda.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    {{-- Coluna lateral (gestor/técnico) --}}
    @if ($role !== UserRole::Cliente)
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Taxa de resolução</div>
                <div class="card-body text-center">
                    <div class="ring" style="--p:{{ $metrics['resolution'] }}"><div><span class="pct">{{ $metrics['resolution'] }}%</span><span class="text-secondary small">resolvidos</span></div></div>
                    <p class="text-secondary small mt-3 mb-0">{{ $metrics['solved'] }} de {{ $metrics['total'] }} chamados concluídos.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Quadro da equipe</div>
                <div class="card-body">
                    <div class="kb-mini">
                        @foreach (['todo' => 'A fazer', 'doing' => 'Em andamento', 'done' => 'Concluído'] as $k => $lbl)
                            <div class="col"><div class="n">{{ $kanban[$k] ?? 0 }}</div><div class="t">{{ $lbl }}</div></div>
                        @endforeach
                    </div>
                    <a href="{{ route('agenda.index') }}" class="small text-decoration-none d-block mt-3">abrir quadro →</a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Chamados por status</div>
                <div class="card-body">
                    @forelse ($byStatus as $r)
                        <div class="bar-row">
                            <span class="name"><span class="badge bg-{{ $r['color'] }}">{{ $r['label'] }}</span></span>
                            <span class="bar-track"><span class="bar-fill" style="width: {{ $metrics['total'] ? round($r['count'] / $metrics['total'] * 100) : 0 }}%"></span></span>
                            <span class="num">{{ $r['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sem dados.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">Próximos prazos</div>
                <div class="list-group list-group-flush">
                    @forelse ($upcoming as $t)
                        <a href="{{ route('tickets.show', $t->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="background: transparent">
                            <span class="text-truncate" style="max-width: 200px"><span class="text-muted me-1">#{{ $t->id }}</span>{{ $t->title }}</span>
                            <span class="small {{ $t->isOverdue() ? 'text-danger fw-semibold' : 'text-secondary' }}">{{ $t->dueDate->format('d/m H:i') }}</span>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small" style="background: transparent">Nenhum prazo próximo.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
