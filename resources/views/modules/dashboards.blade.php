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
            @if (! empty($overview['porSeveridade']))
                <div class="card mb-4">
                    <div class="card-header bg-transparent fw-semibold"><i class="bi bi-pie-chart me-1"></i> Problemas por severidade</div>
                    <div class="card-body"><div id="sevDonut" data-sev='@json($overview['porSeveridade'])'></div></div>
                </div>
            @endif
            <div class="card">
                <div class="card-header bg-transparent fw-semibold"><i class="bi bi-hdd-stack me-1"></i> Hosts <span class="text-secondary small fw-normal">(clique num host)</span></div>
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

{{-- Modal: detalhe do host (gauges + histórico) --}}
<div class="modal fade" id="hostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-hdd me-2 text-success"></i><span id="hm_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row text-center g-2">
                    <div class="col-4"><div id="g_cpu"></div><div class="small text-secondary">CPU</div></div>
                    <div class="col-4"><div id="g_ram"></div><div class="small text-secondary">RAM</div></div>
                    <div class="col-4"><div id="g_disk"></div><div class="small text-secondary">Disco</div></div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold small">Histórico de CPU e RAM</span>
                    <select id="hm_hours" class="form-select form-select-sm" style="width:auto">
                        <option value="6" selected>Últimas 6h</option>
                        <option value="12">Últimas 12h</option>
                        <option value="24">Últimas 24h</option>
                    </select>
                </div>
                <div id="hm_line" style="min-height:260px"></div>
                <div id="hm_msg" class="text-secondary small text-center py-5 d-none"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
<script>
(function () {
    // ---- Auto-refresh (mantém) ----
    var box = document.getElementById('autoRefresh'), t;
    function schedule() { clearTimeout(t); if (box && box.checked) { t = setTimeout(function () { location.reload(); }, 60000); } }
    if (box) box.addEventListener('change', schedule);
    schedule();

    if (!window.ApexCharts) return;
    var HISTORY_URL = "{{ route('modules.analytics.history', ['hostid' => '__ID__']) }}";

    function colorFor(v) { if (v == null) return '#9aa1a8'; return v >= 90 ? '#dc3545' : (v >= 75 ? '#f59e0b' : '#0a9d5a'); }
    function sevColor(label) {
        var m = { 'Desastre':'#dc3545','Alto':'#ef4444','Médio':'#f59e0b','Atenção':'#f59e0b','Informação':'#3b82f6','Não classificado':'#9aa1a8' };
        return m[label] || '#6c757d';
    }

    // ---- Rosca de severidade ----
    var donutEl = document.getElementById('sevDonut');
    if (donutEl) {
        var sev = {}; try { sev = JSON.parse(donutEl.dataset.sev || '{}'); } catch (e) {}
        var labels = Object.keys(sev), values = labels.map(function (k) { return sev[k]; });
        if (values.length) {
            new ApexCharts(donutEl, {
                chart: { type: 'donut', height: 250 },
                series: values, labels: labels, colors: labels.map(sevColor),
                legend: { position: 'bottom' }, stroke: { width: 0 },
                dataLabels: { enabled: true, formatter: function (val, o) { return o.w.config.series[o.seriesIndex]; } },
                plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Problemas' } } } } },
            }).render();
        }
    }

    // ---- Modal do host: gauges + linha ----
    var modalEl = document.getElementById('hostModal');
    if (!modalEl) return;
    var hostModal = new bootstrap.Modal(modalEl);
    var current = { id: null, cpu: null, ram: null, disk: null };
    var charts = {};

    function gauge(sel, value) {
        if (charts[sel]) { charts[sel].destroy(); }
        charts[sel] = new ApexCharts(document.querySelector(sel), {
            chart: { type: 'radialBar', height: 160, sparkline: { enabled: true } },
            series: [value == null ? 0 : value], colors: [colorFor(value)],
            plotOptions: { radialBar: { hollow: { size: '55%' },
                dataLabels: { name: { show: false }, value: { offsetY: 6, fontSize: '17px', fontWeight: 700,
                    formatter: function () { return value == null ? '—' : Math.round(value) + '%'; } } } } },
        });
        charts[sel].render();
    }

    var lineChart = null;
    function renderLine(cpu, ram) {
        if (lineChart) { lineChart.destroy(); }
        lineChart = new ApexCharts(document.querySelector('#hm_line'), {
            chart: { type: 'area', height: 260, toolbar: { show: false }, animations: { enabled: false } },
            series: [{ name: 'CPU', data: cpu }, { name: 'RAM', data: ram }],
            colors: ['#067a45', '#4338ca'], stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
            dataLabels: { enabled: false }, legend: { position: 'top' },
            xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
            yaxis: { min: 0, max: 100, labels: { formatter: function (v) { return Math.round(v) + '%'; } } },
            tooltip: { x: { format: 'dd/MM HH:mm' } },
        });
        lineChart.render();
    }

    function loadHistory() {
        var hours = document.getElementById('hm_hours').value;
        var msg = document.getElementById('hm_msg'), lineBox = document.getElementById('hm_line');
        msg.classList.remove('d-none'); msg.textContent = 'Carregando histórico…'; lineBox.style.display = 'none';
        fetch(HISTORY_URL.replace('__ID__', current.id) + '?hours=' + hours, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var has = (d.cpu && d.cpu.length) || (d.ram && d.ram.length);
                if (!has) { msg.textContent = 'Sem histórico disponível para este host.'; return; }
                msg.classList.add('d-none'); lineBox.style.display = '';
                renderLine(d.cpu || [], d.ram || []);
            })
            .catch(function () { msg.textContent = 'Não foi possível carregar o histórico.'; });
    }

    window.openHost = function (row) {
        current.id = row.dataset.id;
        current.cpu = row.dataset.cpu === '' ? null : +row.dataset.cpu;
        current.ram = row.dataset.ram === '' ? null : +row.dataset.ram;
        current.disk = row.dataset.disk === '' ? null : +row.dataset.disk;
        document.getElementById('hm_name').textContent = row.dataset.name;
        hostModal.show();
    };

    // Renderiza depois que o modal está visível (charts precisam de largura).
    modalEl.addEventListener('shown.bs.modal', function () {
        gauge('#g_cpu', current.cpu); gauge('#g_ram', current.ram); gauge('#g_disk', current.disk);
        loadHistory();
    });
    document.getElementById('hm_hours').addEventListener('change', loadHistory);
})();
</script>
@endpush
@endsection
