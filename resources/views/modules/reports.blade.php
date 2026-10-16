@extends('layouts.app')

@section('title', 'Relatórios — FOURLINE Connect')

@push('head')
<style>
    .stat-card { border:1px solid var(--bs-border-color); border-radius:14px; padding:.9rem 1.1rem; height:100%; background:var(--surface); }
    .stat-card .v { font-size:1.6rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1.05; }
    .stat-card .l { font-size:.78rem; color:var(--bs-secondary-color); }
    .bar-row { display:flex; align-items:center; gap:.75rem; margin-bottom:.55rem; }
    .bar-row .name { width:150px; flex:0 0 auto; font-size:.85rem; }
    .bar-track { flex:1 1 auto; background:var(--bs-secondary-bg); border-radius:999px; height:12px; overflow:hidden; }
    .bar-fill { height:100%; border-radius:999px; background:linear-gradient(90deg,#0a9d5a,#A8CF45); min-width:2px; display:block; }
    .bar-row .num { width:50px; text-align:right; font-weight:600; font-size:.85rem; }
    .rep-tabs .nav-link { color:var(--bs-secondary-color); font-weight:500; border:none; border-bottom:2px solid transparent; padding:.6rem 1rem; }
    .rep-tabs .nav-link:hover { color:#067a45; border-bottom-color:rgba(6,122,69,.3); }
    .rep-tabs .nav-link.active { font-weight:600; color:#067a45; border-bottom-color:#067a45; background:transparent; }
    .filters .form-label { font-size:.75rem; color:var(--bs-secondary-color); margin-bottom:.15rem; }
    .mini-bar { height:6px; border-radius:999px; background:var(--bs-secondary-bg); overflow:hidden; min-width:60px; }
    .mini-bar > span { display:block; height:100%; background:#068A4F; }
    .badge-marca { background:rgba(6,122,69,.12); color:#067a45; font-weight:600; padding:.35rem .65rem; border-radius:6px; font-size:.85rem; }
    [data-bs-theme="dark"] .badge-marca { background:rgba(6,122,69,.25); color:#7bd49f; }
    .report-print-head { display:none; }
    @media print {
        .app-shell .sidebar, .app-shell .topbar, .sidebar-backdrop, .no-print { display:none !important; }
        .app-shell .content { margin:0 !important; }
        .app-shell .page { padding:0 !important; }
        html, body { background:#fff !important; color:#111 !important; }
        .report-print-head { display:block !important; border-bottom:2px solid #067a45; padding-bottom:.5rem; margin-bottom:1rem; }
        .card, .stat-card { background:#fff !important; color:#111 !important; border:1px solid #ccc !important; box-shadow:none !important; break-inside:avoid; }
        .bar-fill, .bar-track, .badge, .mini-bar span, [class*="bg-"] { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
        a[href]::after { content:''; }
        @page { margin:1.2cm; }
    }
</style>
@endpush

@section('content')
@php
    // Filtros atuais (para manter nos links das abas/exportação).
    $q = array_filter([
        'periodo' => $f['periodo'],
        'de' => $f['periodo'] === 'personalizado' ? $f['de']->format('Y-m-d') : null,
        'ate' => $f['periodo'] === 'personalizado' ? $f['ate']->format('Y-m-d') : null,
        'cliente' => $f['cliente'], 'tecnico' => $f['tecnico'], 'categoria' => $f['categoria'], 'situacao' => $f['situacao'],
    ], fn ($v) => $v !== null && $v !== '');
    $abas = [
        'geral' => ['bi-speedometer2', 'Visão geral'],
        'clientes' => ['bi-buildings', 'Por cliente'],
        'tecnicos' => ['bi-person-gear', 'Por técnico'],
        'categorias' => ['bi-tags', 'Por categoria'],
        'chamados' => ['bi-list-ul', 'Chamados'],
        'marcas' => ['bi-tag', 'Marcas (inventário)'],
    ];
    $periodos = ['30d' => 'Últimos 30 dias', 'mes' => 'Este mês', 'mes_passado' => 'Mês passado', '90d' => 'Últimos 90 dias', 'ano' => 'Este ano', 'tudo' => 'Todo o período', 'personalizado' => 'Personalizado…'];
    $slaClass = fn ($v) => $v === null ? 'bg-secondary-subtle text-secondary-emphasis' : ($v >= 90 ? 'bg-success-subtle text-success-emphasis' : ($v >= 70 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger-emphasis'));
    $exportavel = in_array($aba, ['chamados', 'clientes', 'tecnicos', 'categorias'], true);
    $periodoTxt = $f['periodo'] === 'tudo' ? 'Todo o período' : $f['de']->format('d/m/Y').' a '.$f['ate']->format('d/m/Y');
@endphp

<div class="report-print-head">
    <div class="d-flex justify-content-between align-items-end">
        <div>
            <div style="font-family:'Rajdhani',sans-serif;font-weight:800;font-size:1.4rem;color:#067a45">FOURLINE <span style="color:#A8CF45">CONNECT</span></div>
            <div style="font-weight:700">Relatório gerencial — {{ $abas[$aba][1] }}</div>
            <div class="small">Período: {{ $periodoTxt }}@if ($f['cliente']) · Cliente: {{ $f['cliente'] }}@endif @if ($f['tecnico']) · Técnico: {{ $f['tecnico'] }}@endif</div>
        </div>
        <div class="text-end small">Gerado em {{ now()->format('d/m/Y H:i') }}<br>por {{ auth()->user()->name }}</div>
    </div>
</div>

<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <h1 class="h4 mb-0">Relatórios</h1>
        <p class="text-secondary small mb-0">Atendimento e infraestrutura · {{ $periodoTxt }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">
        @if ($f['cliente'] !== '')
            <a href="{{ route('reports.client', $q) }}" target="_blank" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-person me-1"></i> Relatório do cliente (PDF)
            </a>
        @endif
        @if ($exportavel)
            <a href="{{ route('reports.export', $q + ['tipo' => $aba]) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar Excel
            </a>
        @endif
        <button type="button" class="btn btn-success" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir / PDF
        </button>
    </div>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('modules.reports') }}" class="card mb-3 no-print filters">
    <input type="hidden" name="aba" value="{{ $aba }}">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Período</label>
                <select name="periodo" id="repPeriodo" class="form-select form-select-sm">
                    @foreach ($periodos as $k => $label)
                        <option value="{{ $k }}" @selected($f['periodo'] === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2 rep-custom {{ $f['periodo'] === 'personalizado' ? '' : 'd-none' }}">
                <label class="form-label">De</label>
                <input type="date" name="de" value="{{ $f['de']->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3 col-xl-2 rep-custom {{ $f['periodo'] === 'personalizado' ? '' : 'd-none' }}">
                <label class="form-label">Até</label>
                <input type="date" name="ate" value="{{ $f['ate']->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Cliente</label>
                <select name="cliente" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($opcoes['clientes'] as $o)<option value="{{ $o }}" @selected($f['cliente'] === $o)>{{ $o }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Técnico</label>
                <select name="tecnico" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($opcoes['tecnicos'] as $o)<option value="{{ $o }}" @selected($f['tecnico'] === $o)>{{ $o }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Categoria</label>
                <select name="categoria" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($opcoes['categorias'] as $o)<option value="{{ $o }}" @selected($f['categoria'] === $o)>{{ $o }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Situação</label>
                <select name="situacao" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="abertos" @selected($f['situacao'] === 'abertos')>Em aberto</option>
                    <option value="resolvidos" @selected($f['situacao'] === 'resolvidos')>Resolvidos</option>
                    <option value="atrasados" @selected($f['situacao'] === 'atrasados')>Atrasados (SLA)</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button class="btn btn-success btn-sm"><i class="bi bi-funnel me-1"></i>Aplicar</button>
                <a href="{{ route('modules.reports', ['aba' => $aba]) }}" class="btn btn-link btn-sm text-secondary">Limpar</a>
            </div>
        </div>
    </div>
</form>

{{-- Indicadores do recorte --}}
<div class="row g-2 mb-3">
    @php
        $cards = [
            ['Chamados', $kpi['total'], ''],
            ['Em aberto', $kpi['abertos'], 'text-warning'],
            ['Resolvidos', $kpi['resolvidos'].' ('.$kpi['resolucao'].'%)', 'text-success'],
            ['Atrasados (SLA)', $kpi['atrasados'], 'text-danger'],
            ['Tempo médio de solução', $kpi['tempo'], ''],
            ['SLA cumprido', $kpi['sla'] === null ? '—' : $kpi['sla'].'%', ''],
        ];
    @endphp
    @foreach ($cards as [$l, $v, $cls])
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card"><div class="v {{ $cls }}">{{ $v }}</div><div class="l">{{ $l }}</div></div>
        </div>
    @endforeach
</div>

{{-- Abas --}}
<ul class="nav rep-tabs mb-3 no-print border-bottom">
    @foreach ($abas as $k => [$icon, $label])
        <li class="nav-item">
            <a class="nav-link {{ $aba === $k ? 'active' : '' }}" href="{{ route('modules.reports', $q + ['aba' => $k]) }}"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</a>
        </li>
    @endforeach
</ul>

@if ($aba === 'geral')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-transparent d-flex justify-content-between">
                    <span class="fw-semibold">Abertos × resolvidos</span><span class="small text-secondary">{{ $serie['unidade'] }}</span>
                </div>
                <div class="card-body">
                    @if (array_sum($serie['abertos']) + array_sum($serie['resolvidos']) === 0)
                        <p class="text-secondary small mb-0">Sem chamados no período.</p>
                    @else
                        <div style="height:260px"><canvas id="repSerie" role="img" aria-label="Chamados abertos e resolvidos no período"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold">Por situação</div>
                <div class="card-body">
                    @forelse ($byStatus as $r)
                        <div class="bar-row">
                            <span class="name"><span class="badge bg-{{ $r['color'] }}">{{ $r['label'] }}</span></span>
                            <span class="bar-track"><span class="bar-fill" style="width: {{ $kpi['total'] ? round($r['count'] / $kpi['total'] * 100) : 0 }}%"></span></span>
                            <span class="num">{{ $r['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sem dados.</p>
                    @endforelse
                    <hr>
                    <div class="small fw-semibold mb-2">Por prioridade</div>
                    @forelse ($byPriority as $r)
                        <div class="bar-row">
                            <span class="name"><span class="badge bg-{{ $r['color'] }}-subtle text-{{ $r['color'] }}-emphasis">{{ $r['label'] }}</span></span>
                            <span class="bar-track"><span class="bar-fill" style="width: {{ $kpi['total'] ? round($r['count'] / $kpi['total'] * 100) : 0 }}%"></span></span>
                            <span class="num">{{ $r['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sem dados.</p>
                    @endforelse
                    @if ($byType->isNotEmpty())
                        <div class="small text-secondary mt-2">
                            @foreach ($byType as $r){{ $r['label'] }}: <strong>{{ $r['count'] }}</strong>@if (! $loop->last) · @endif @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Clientes com mais chamados</span>
                    <a href="{{ route('modules.reports', $q + ['aba' => 'clientes']) }}" class="small no-print">ver todos</a>
                </div>
                <div class="card-body">
                    @forelse ($topClientes as $r)
                        <div class="bar-row">
                            <span class="name text-truncate" title="{{ $r['nome'] }}">{{ $r['nome'] }}</span>
                            <span class="bar-track"><span class="bar-fill" style="width: {{ round($r['total'] / max(1, $topClientes->max('total')) * 100) }}%"></span></span>
                            <span class="num">{{ $r['total'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sem chamados no período.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        const el = document.getElementById('repSerie');
        if (!el || !window.Chart) return;
        const s = @json($serie);
        const css = getComputedStyle(document.documentElement);
        Chart.defaults.color = css.getPropertyValue('--muted-c').trim() || '#64748b';
        new Chart(el, {
            type: 'bar',
            data: { labels: s.labels, datasets: [
                { label: 'Abertos', data: s.abertos, backgroundColor: '#A8CF45', borderRadius: 5 },
                { label: 'Resolvidos', data: s.resolvidos, backgroundColor: '#068A4F', borderRadius: 5 },
            ] },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: css.getPropertyValue('--border-c').trim() || '#e6eaf0' } } } },
        });
    })();
    </script>
    @endpush

@elseif (in_array($aba, ['clientes', 'tecnicos', 'categorias'], true))
    @php
        $col = ['clientes' => 'Cliente', 'tecnicos' => 'Técnico', 'categorias' => 'Categoria'][$aba];
        $filtro = ['clientes' => 'cliente', 'tecnicos' => 'tecnico', 'categorias' => 'categoria'][$aba];
    @endphp
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ $col }}</th>
                        <th class="text-end">Chamados</th>
                        <th class="text-end">Em aberto</th>
                        <th class="text-end">Resolvidos</th>
                        <th class="text-end">Atrasados</th>
                        <th style="min-width:140px">% resolvidos</th>
                        <th class="text-end text-nowrap">Tempo médio</th>
                        <th class="text-center">SLA</th>
                        @if ($aba === 'clientes')<th class="no-print"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('modules.reports', array_merge($q, [$filtro => $r['nome'], 'aba' => 'chamados'])) }}" class="text-reset text-decoration-none" title="Ver chamados">{{ $r['nome'] }}</a>
                            </td>
                            <td class="text-end">{{ $r['total'] }}</td>
                            <td class="text-end text-warning-emphasis">{{ $r['abertos'] }}</td>
                            <td class="text-end text-success-emphasis">{{ $r['resolvidos'] }}</td>
                            <td class="text-end {{ $r['atrasados'] ? 'text-danger fw-semibold' : 'text-secondary' }}">{{ $r['atrasados'] }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="mini-bar flex-grow-1"><span style="width:{{ $r['resolucao'] }}%"></span></div>
                                    <span class="small fw-semibold" style="width:38px">{{ $r['resolucao'] }}%</span>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">{{ $r['tempo'] }}</td>
                            <td class="text-center"><span class="badge {{ $slaClass($r['sla']) }}" title="Base: {{ $r['sla_base'] }} chamado(s) resolvido(s) com prazo">{{ $r['sla'] === null ? '—' : $r['sla'].'%' }}</span></td>
                            @if ($aba === 'clientes')
                                <td class="text-end no-print">
                                    <a href="{{ route('reports.client', array_merge($q, ['cliente' => $r['nome']])) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Relatório do cliente (PDF)"><i class="bi bi-file-earmark-person"></i></a>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Sem chamados neste filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-secondary mt-2 no-print"><i class="bi bi-info-circle me-1"></i>SLA = chamados resolvidos dentro do prazo, entre os que tinham prazo definido. Clique no nome para ver os chamados.</p>

@elseif ($aba === 'chamados')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr><th>Nº</th><th>Título</th><th>Cliente</th><th>Técnico</th><th>Categoria</th><th>Prioridade</th><th>Situação</th><th class="text-nowrap">Aberto em</th><th class="text-nowrap">Solucionado</th><th>SLA</th></tr>
                </thead>
                <tbody>
                    @forelse ($chamados as $t)
                        @php
                            $sla = $t->dueDate === null ? ['Sem prazo', 'secondary']
                                : ($t->solvedAt !== null ? ($t->solvedAt->lte($t->dueDate) ? ['No prazo', 'success'] : ['Fora do prazo', 'danger'])
                                : ($t->isOverdue() ? ['Atrasado', 'danger'] : ['Em andamento', 'warning']));
                        @endphp
                        <tr>
                            <td class="text-secondary">#{{ $t->id }}</td>
                            <td><a href="{{ route('tickets.show', $t->id) }}" class="fw-semibold text-decoration-none">{{ \Illuminate\Support\Str::limit($t->title, 60) }}</a></td>
                            <td class="text-nowrap">{{ $t->entity }}</td>
                            <td class="text-nowrap">{{ $t->technicianName ?: '—' }}</td>
                            <td>{{ $t->category ?: '—' }}</td>
                            <td><span class="badge bg-{{ $t->priority->color() }}-subtle text-{{ $t->priority->color() }}-emphasis">{{ $t->priority->label() }}</span></td>
                            <td><span class="badge bg-{{ $t->status->color() }}">{{ $t->status->label() }}</span></td>
                            <td class="text-nowrap">{{ $t->createdAt->format('d/m/Y H:i') }}</td>
                            <td class="text-nowrap">{{ $t->solvedAt?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td><span class="badge bg-{{ $sla[1] }}-subtle text-{{ $sla[1] }}-emphasis">{{ $sla[0] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">Sem chamados neste filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($chamados->hasPages())
        <div class="mt-3 d-flex justify-content-center no-print">{{ $chamados->onEachSide(1)->links() }}</div>
    @endif

@elseif ($aba === 'marcas')
    <p class="small text-secondary"><i class="bi bi-info-circle me-1"></i>Equipamentos do inventário atual (os filtros de período/cliente valem só para chamados).</p>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="v text-success">{{ $metricasMarcas['total_ativos'] }}</div><div class="l">Equipamentos inventariados</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="v">{{ $metricasMarcas['total_marcas'] }}</div><div class="l">Marcas identificadas</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="v text-truncate" title="{{ $metricasMarcas['marca_lider'] }}">{{ $metricasMarcas['marca_lider'] }}</div><div class="l">Marca líder ({{ $metricasMarcas['marca_lider_count'] }} ativos)</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="v text-secondary">{{ $metricasMarcas['sem_marca'] }}</div><div class="l">Sem marca / genéricos</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold">Distribuição por marca</div>
                <div class="card-body">
                    @forelse ($byMarca as $m)
                        <div class="bar-row">
                            <span class="name text-truncate" title="{{ $m['marca'] }}"><strong>{{ $m['marca'] }}</strong></span>
                            <span class="bar-track"><span class="bar-fill" style="width: {{ $m['pct'] }}%"></span></span>
                            <span class="num text-nowrap" style="width:80px">{{ $m['count'] }} <small class="text-secondary fw-normal">({{ $m['pct'] }}%)</small></span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Nenhuma marca registrada.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>Detalhamento por marca</span>
                    <input type="search" id="marcaFilter" class="form-control form-control-sm no-print" style="max-width:220px" placeholder="Filtrar marcas...">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="marcasTable">
                        <thead><tr><th>Marca</th><th class="text-center">Qtd.</th><th>Tipos</th><th>Clientes</th></tr></thead>
                        <tbody>
                            @forelse ($byMarca as $m)
                                <tr class="marca-row">
                                    <td><span class="badge-marca">{{ $m['marca'] }}</span>
                                        @if (! empty($m['modelos']))<div class="small text-secondary mt-1">{{ implode(', ', array_slice($m['modelos'], 0, 2)) }}</div>@endif
                                    </td>
                                    <td class="text-center fw-bold">{{ $m['count'] }}</td>
                                    <td><div class="d-flex flex-wrap gap-1">@foreach ($m['tipos'] as $tn => $tq)<span class="badge bg-light text-dark border small">{{ $tn }}: <strong>{{ $tq }}</strong></span>@endforeach</div></td>
                                    <td><small class="text-secondary" title="{{ implode(', ', $m['clientes']) }}">{{ count($m['clientes']) }} {{ count($m['clientes']) === 1 ? 'cliente' : 'clientes' }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">Nenhum equipamento no inventário.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    // Período personalizado: mostra/esconde as datas.
    const per = document.getElementById('repPeriodo');
    if (per) per.addEventListener('change', function () {
        document.querySelectorAll('.rep-custom').forEach((el) => el.classList.toggle('d-none', per.value !== 'personalizado'));
    });
    // Filtro rápido da tabela de marcas.
    const mf = document.getElementById('marcaFilter');
    if (mf) mf.addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('#marcasTable .marca-row').forEach((row) => {
            row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
        });
    });
})();
</script>
@endpush
