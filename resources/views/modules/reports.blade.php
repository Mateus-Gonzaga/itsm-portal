@extends('layouts.app')

@section('title', 'Relatórios — FOURLINE Connect')

@push('head')
<style>
    .stat-card { border:1px solid var(--bs-border-color); border-radius:14px; padding:1rem 1.25rem; }
    .stat-card .v { font-size:1.7rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .stat-card .l { font-size:.8rem; color:var(--bs-secondary-color); }
    .bar-row { display:flex; align-items:center; gap:.75rem; margin-bottom:.6rem; }
    .bar-row .name { width:180px; flex:0 0 auto; font-size:.85rem; }
    .bar-track { flex:1 1 auto; background:var(--bs-secondary-bg); border-radius:999px; height:14px; overflow:hidden; }
    .bar-fill { height:100%; border-radius:999px; background:linear-gradient(90deg,#0a9d5a,#A8CF45); min-width:2px; }
    .bar-row .num { width:65px; text-align:right; font-weight:600; font-size:.85rem; }
    .ring { --p:0; width:120px; height:120px; border-radius:50%;
        background:conic-gradient(#068A4F calc(var(--p)*1%), var(--bs-secondary-bg) 0);
        display:flex; align-items:center; justify-content:center; }
    .ring > div { width:88px; height:88px; border-radius:50%; background:var(--bs-body-bg); display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .ring .pct { font-size:1.5rem; font-weight:700; font-family:'Rajdhani',sans-serif; }

    /* Abas personalizadas */
    .nav-tabs .nav-link { color:var(--bs-secondary-color); font-weight:500; border:none; border-bottom:2px solid transparent; padding:.65rem 1.2rem; }
    .nav-tabs .nav-link:hover { color:#067a45; border-bottom-color:rgba(6,122,69,.3); }
    .nav-tabs .nav-link.active { font-weight:600; color:#067a45; border-bottom-color:#067a45; background:transparent; }
    .badge-marca { background:rgba(6,122,69,.12); color:#067a45; font-weight:600; padding:.35rem .65rem; border-radius:6px; font-size:.85rem; }
    [data-bs-theme="dark"] .badge-marca { background:rgba(6,122,69,.25); color:#7bd49f; }

    /* Cabeçalho que só aparece no PDF/impressão */
    .report-print-head { display:none; }
    .report-print-head .brand { font-family:'Rajdhani',sans-serif; font-weight:800; font-size:1.5rem; letter-spacing:.02em; color:#067a45; }
    .report-print-head .brand span { color:#A8CF45; }

    @media print {
        .app-shell .sidebar, .app-shell .topbar, .sidebar-backdrop, .no-print { display:none !important; }
        .app-shell .content { margin:0 !important; }
        .app-shell .page { padding:0 !important; }
        html, body { background:#fff !important; color:#111 !important; }
        .report-print-head { display:block !important; border-bottom:2px solid #067a45; padding-bottom:.6rem; margin-bottom:1rem; }
        .card, .stat-card { background:#fff !important; color:#111 !important; border:1px solid #ccc !important; box-shadow:none !important; break-inside:avoid; }
        .card-header { color:#111 !important; }
        .l, .text-secondary, .text-muted, .card-body p.text-secondary { color:#555 !important; }
        .bar-fill, .bar-track, .badge, .ring, [class*="bg-"] {
            -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important;
        }
        .bar-track { background:#e9ecef !important; }
        a[href]::after { content:''; }
        @page { margin:1.2cm; }
        .tab-content > .tab-pane { display:block !important; opacity:1 !important; visibility:visible !important; }
        .tab-pane + .tab-pane { page-break-before:always; margin-top:2rem; }
    }
</style>
@endpush

@section('content')
{{-- Cabeçalho visível só no PDF/impressão --}}
<div class="report-print-head">
    <div class="d-flex justify-content-between align-items-end">
        <div>
            <div class="brand">FOURLINE <span>CONNECT</span></div>
            <div style="font-size:1.05rem; font-weight:700;">Relatório Gerencial de Atendimento e Ativos</div>
        </div>
        <div class="text-end small" style="color:#555;">
            Gerado em {{ now()->format('d/m/Y H:i') }}<br>
            por {{ auth()->user()->name }}
        </div>
    </div>
</div>

<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <h1 class="h4 mb-0">Relatórios</h1>
        <p class="text-secondary small mb-0">Visão gerencial de atendimento e infraestrutura (dados do GLPI).</p>
    </div>
    <button type="button" class="btn btn-success no-print" onclick="window.print()">
        <i class="bi bi-file-earmark-pdf me-1"></i> Imprimir / Salvar PDF
    </button>
</div>

{{-- NAVEGAÇÃO POR ABAS --}}
<ul class="nav nav-tabs mb-4 no-print" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-atendimento-btn" data-bs-toggle="tab" data-bs-target="#tab-atendimento" type="button" role="tab" aria-controls="tab-atendimento" aria-selected="true">
            <i class="bi bi-headset me-1"></i> Atendimento
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-marcas-btn" data-bs-toggle="tab" data-bs-target="#tab-marcas" type="button" role="tab" aria-controls="tab-marcas" aria-selected="false">
            <i class="bi bi-tag me-1"></i> Marcas
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- ========================================================================= --}}
    {{-- ABA 1: ATENDIMENTO --}}
    {{-- ========================================================================= --}}
    <div class="tab-pane fade show active" id="tab-atendimento" role="tabpanel" aria-labelledby="tab-atendimento-btn">
        <div class="row g-3 mb-4">
            <div class="col-md col-6"><div class="stat-card"><div class="v">{{ $metrics['total'] }}</div><div class="l">Total de chamados</div></div></div>
            <div class="col-md col-6"><div class="stat-card"><div class="v text-warning">{{ $metrics['abertos'] }}</div><div class="l">Em aberto</div></div></div>
            <div class="col-md col-6"><div class="stat-card"><div class="v text-success">{{ $metrics['resolvidos'] }}</div><div class="l">Resolvidos</div></div></div>
            <div class="col-md col-6"><div class="stat-card"><div class="v text-danger">{{ $metrics['atrasados'] }}</div><div class="l">Atrasados (SLA)</div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent fw-semibold">Taxa de resolução</div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        <div class="ring" style="--p:{{ $metrics['resolucao'] }}"><div><span class="pct">{{ $metrics['resolucao'] }}%</span><span class="text-secondary small">resolvidos</span></div></div>
                        <p class="text-secondary small mt-3 mb-0 text-center">{{ $metrics['resolvidos'] }} de {{ $metrics['total'] }} chamados concluídos.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent fw-semibold">Por status</div>
                    <div class="card-body">
                        @forelse ($byStatus as $r)
                            <div class="bar-row">
                                <span class="name"><span class="badge bg-{{ $r['color'] }}">{{ $r['label'] }}</span></span>
                                <span class="bar-track"><span class="bar-fill" style="width: {{ $total ? round($r['count'] / $total * 100) : 0 }}%"></span></span>
                                <span class="num">{{ $r['count'] }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sem dados.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent fw-semibold">Por prioridade</div>
                    <div class="card-body">
                        @forelse ($byPriority as $r)
                            <div class="bar-row">
                                <span class="name"><span class="badge bg-{{ $r['color'] }}-subtle text-{{ $r['color'] }}-emphasis">{{ $r['label'] }}</span></span>
                                <span class="bar-track"><span class="bar-fill" style="width: {{ $total ? round($r['count'] / $total * 100) : 0 }}%"></span></span>
                                <span class="num">{{ $r['count'] }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sem dados.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-transparent fw-semibold">Chamados por cliente (top 10)</div>
                    <div class="card-body">
                        @forelse ($byClient as $r)
                            <div class="bar-row">
                                <span class="name text-truncate" title="{{ $r['label'] }}">{{ $r['label'] }}</span>
                                <span class="bar-track"><span class="bar-fill" style="width: {{ $total ? round($r['count'] / max(1,$byClient->max('count')) * 100) : 0 }}%"></span></span>
                                <span class="num">{{ $r['count'] }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sem dados.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- ABA 2: MARCAS (FABRICANTES DE EQUIPAMENTOS) --}}
    {{-- ========================================================================= --}}
    <div class="tab-pane fade" id="tab-marcas" role="tabpanel" aria-labelledby="tab-marcas-btn">
        {{-- KPIs de Marcas --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="v text-success">{{ $metricasMarcas['total_ativos'] }}</div>
                    <div class="l">Equipamentos inventariados</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="v" style="color:#067a45;">{{ $metricasMarcas['total_marcas'] }}</div>
                    <div class="l">Marcas identificadas</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="v text-primary text-truncate" title="{{ $metricasMarcas['marca_lider'] }}">{{ $metricasMarcas['marca_lider'] }}</div>
                    <div class="l">Marca líder ({{ $metricasMarcas['marca_lider_count'] }} ativos)</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="v text-secondary">{{ $metricasMarcas['sem_marca'] }}</div>
                    <div class="l">Sem marca / genéricos</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Distribuição de Equipamentos por Marca --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-pie-chart me-1 text-success"></i> Distribuição por Marca</span>
                        <span class="badge bg-secondary-subtle text-secondary small">{{ count($byMarca) }} marcas</span>
                    </div>
                    <div class="card-body">
                        @forelse ($byMarca as $m)
                            <div class="bar-row mb-3">
                                <span class="name text-truncate" title="{{ $m['marca'] }}">
                                    <i class="bi bi-tag-fill text-success small me-1"></i>
                                    <strong>{{ $m['marca'] }}</strong>
                                </span>
                                <span class="bar-track">
                                    <span class="bar-fill" style="width: {{ $m['pct'] }}%"></span>
                                </span>
                                <span class="num text-nowrap" style="width:75px;">
                                    {{ $m['count'] }} <small class="text-secondary fw-normal">({{ $m['pct'] }}%)</small>
                                </span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Nenhuma marca registrada.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Tabela Detalhada com Filtro --}}
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header bg-transparent fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><i class="bi bi-table me-1 text-success"></i> Detalhamento por Marca</span>
                        <div class="input-group input-group-sm no-print" style="max-width:240px">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="marcaFilter" class="form-control" placeholder="Filtrar marcas...">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="marcasTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Marca</th>
                                        <th class="text-center">Qtd.</th>
                                        <th class="text-center">% Total</th>
                                        <th>Tipos de Equipamento</th>
                                        <th>Clientes / Filiais</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($byMarca as $m)
                                        <tr class="marca-row">
                                            <td>
                                                <span class="badge-marca">
                                                    <i class="bi bi-tag me-1"></i>{{ $m['marca'] }}
                                                </span>
                                                @if (!empty($m['modelos']))
                                                    <div class="small text-secondary mt-1">
                                                        {{ implode(', ', array_slice($m['modelos'], 0, 2)) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center fw-bold">{{ $m['count'] }}</td>
                                            <td class="text-center text-nowrap">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <div class="progress flex-grow-1" style="height:6px; max-width:60px;">
                                                        <div class="progress-bar bg-success" style="width: {{ $m['pct'] }}%"></div>
                                                    </div>
                                                    <span class="small fw-semibold">{{ $m['pct'] }}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach ($m['tipos'] as $tipoNome => $tipoQtd)
                                                        <span class="badge bg-light text-dark border small" title="{{ $tipoNome }}">
                                                            {{ $tipoNome }}: <strong>{{ $tipoQtd }}</strong>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-secondary" title="{{ implode(', ', $m['clientes']) }}">
                                                    {{ count($m['clientes']) }} {{ count($m['clientes']) === 1 ? 'cliente' : 'clientes' }}
                                                    @if (count($m['clientes']) > 0)
                                                        <span class="text-muted d-block" style="font-size:0.75rem;">
                                                            {{ Str::limit(implode(', ', $m['clientes']), 35) }}
                                                        </span>
                                                    @endif
                                                </small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                Nenhum equipamento cadastrado no inventário.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filterInput = document.getElementById('marcaFilter');
        if (!filterInput) return;

        filterInput.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#marcasTable .marca-row');

            rows.forEach(function (row) {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });
</script>
@endpush
