@extends('layouts.app')

@section('title', 'Dashboards — Monitoramento')

@php
    // Render de uma tabela de hosts (nome, disponibilidade, CPU/RAM/Disco).
    $metric = function ($v) {
        if ($v === null) return '<span class="text-muted">—</span>';
        $c = $v >= 90 ? 'danger' : ($v >= 75 ? 'warning' : 'success');
        return '<span class="badge bg-'.$c.'-subtle text-'.$c.'-emphasis">'.$v.'%</span>';
    };
    $avail = function ($a) {
        if ($a === 1) return '<span class="dot bg-success"></span>Disponível';
        if ($a === 2) return '<span class="dot bg-danger"></span>Indisponível';
        return '<span class="dot bg-secondary"></span><span class="text-muted">Desconhecido</span>';
    };
@endphp

@push('head')
<style>
    .mon-card { border:1px solid var(--bs-border-color); border-radius:14px; padding:1rem 1.25rem; display:flex; align-items:center; gap:.9rem; }
    .mon-card .ic { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; color:#fff; flex:0 0 auto; }
    .mon-card .v { font-size:1.6rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .mon-card .l { font-size:.8rem; color:var(--bs-secondary-color); }
    .ic-green { background:linear-gradient(135deg,#0a9d5a,#067a45); }
    .ic-gray  { background:linear-gradient(135deg,#9aa1a8,#6c757d); }
    .ic-red   { background:linear-gradient(135deg,#e35d6a,#dc3545); }
    .dot { display:inline-block; width:.6rem; height:.6rem; border-radius:50%; margin-right:.35rem; }
    .nav-tabs .nav-link.active { font-weight:600; color:#067a45; border-bottom-color:#067a45; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Dashboards — Monitoramento
            @if ($selected)<span class="text-secondary">› {{ $selected }}</span>@endif
        </h1>
        <p class="text-secondary small mb-0">Status dos hosts e alertas em tempo real (Zabbix).</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('modules.analytics') }}" class="btn btn-sm {{ $selected ? 'btn-outline-secondary' : 'btn-primary' }}"><i class="bi bi-grid me-1"></i> Visão Geral</a>
        @if (!empty($clientes))
            <div class="input-group input-group-sm" style="width:auto">
                <span class="input-group-text"><i class="bi bi-building"></i></span>
                <select class="form-select" style="min-width:190px" onchange="if(this.value) location.href='{{ route('modules.analytics') }}?cliente='+encodeURIComponent(this.value); else location.href='{{ route('modules.analytics') }}';">
                    <option value="">— escolher cliente —</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c }}" @selected($selected === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 small text-secondary">
    <i class="bi bi-arrow-repeat"></i>
    <span>Atualizado às <strong>{{ now()->format('H:i:s') }}</strong> · atualiza sozinho a cada <strong>60s</strong></span>
    <div class="form-check form-switch ms-1 mb-0"><input class="form-check-input" type="checkbox" id="autoRefresh" checked><label class="form-check-label" for="autoRefresh">auto</label></div>
</div>

@if ($error)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> {{ $error }}</div>
@endif

@if (empty($clientes) && ! $error)
    <div class="alert alert-light border small">
        <i class="bi bi-info-circle me-1"></i> Para navegar por cliente, organize os hosts no Zabbix em grupos no padrão
        <code>Clientes/&lt;Cliente&gt;/Servidores</code> e <code>Clientes/&lt;Cliente&gt;/Caixas</code>. Sem isso, fica só a Visão Geral.
    </div>
@endif

@if ($mode === 'geral')
    {{-- ===== VISÃO GERAL ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-3 col-6"><div class="mon-card"><div class="ic ic-green"><i class="bi bi-hdd-stack"></i></div><div><div class="v">{{ $overview['hosts'] }}</div><div class="l">Hosts monitorados</div></div></div></div>
        <div class="col-sm-3 col-6"><div class="mon-card"><div class="ic ic-green"><i class="bi bi-check-circle"></i></div><div><div class="v">{{ $overview['disponiveis'] }}</div><div class="l">Disponíveis</div></div></div></div>
        <div class="col-sm-3 col-6"><div class="mon-card"><div class="ic ic-gray"><i class="bi bi-question-circle"></i></div><div><div class="v">{{ $overview['indisponiveis'] }}</div><div class="l">Indisponíveis</div></div></div></div>
        <div class="col-sm-3 col-6"><div class="mon-card"><div class="ic ic-red"><i class="bi bi-exclamation-triangle"></i></div><div><div class="v">{{ $overview['problemas'] }}</div><div class="l">Problemas ativos</div></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i> Problemas ativos</div>
                <div class="card-body">@include('modules.partials.problems', ['problems' => $problems])</div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold"><i class="bi bi-hdd-stack me-1"></i> Hosts</div>
                <div class="card-body">@include('modules.partials.hosts', ['hosts' => $hosts, 'metric' => $metric, 'avail' => $avail])</div>
            </div>
        </div>
    </div>
@else
    {{-- ===== POR CLIENTE ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6"><div class="mon-card"><div class="ic ic-green"><i class="bi bi-hdd-network"></i></div><div><div class="v">{{ $resumo['servOn'] }}<span class="text-muted fs-6">/{{ $resumo['servOn'] + $resumo['servOff'] }}</span></div><div class="l">Servidores online</div></div></div></div>
        <div class="col-md-3 col-6"><div class="mon-card"><div class="ic ic-green"><i class="bi bi-pc-display"></i></div><div><div class="v">{{ $resumo['caixaOn'] }}<span class="text-muted fs-6">/{{ $resumo['caixaOn'] + $resumo['caixaOff'] }}</span></div><div class="l">Caixas online</div></div></div></div>
        <div class="col-md-3 col-6"><div class="mon-card"><div class="ic ic-gray"><i class="bi bi-power"></i></div><div><div class="v">{{ $resumo['servOff'] + $resumo['caixaOff'] }}</div><div class="l">Offline</div></div></div></div>
        <div class="col-md-3 col-6"><div class="mon-card"><div class="ic ic-red"><i class="bi bi-exclamation-triangle"></i></div><div><div class="v">{{ $resumo['alertas'] }}</div><div class="l">Alertas</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header bg-transparent">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-resumo" type="button">Resumo</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-serv" type="button">Servidores <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $servidores->count() }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-caixas" type="button">Caixas <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $caixas->count() }}</span></button></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="t-resumo">@include('modules.partials.problems', ['problems' => $problems])</div>
                <div class="tab-pane fade" id="t-serv">@include('modules.partials.hosts', ['hosts' => $servidores, 'metric' => $metric, 'avail' => $avail])</div>
                <div class="tab-pane fade" id="t-caixas">@include('modules.partials.hosts', ['hosts' => $caixas, 'metric' => $metric, 'avail' => $avail])</div>
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
(function () {
    var box = document.getElementById('autoRefresh'), t;
    function schedule() { clearTimeout(t); if (box && box.checked) { t = setTimeout(function () { location.reload(); }, 60000); } }
    if (box) box.addEventListener('change', schedule);
    schedule();
})();
</script>
@endpush
@endsection
