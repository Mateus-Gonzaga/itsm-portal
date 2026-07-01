@extends('layouts.app')

@section('title', 'Relatórios — FOURLINE Connect')

@push('head')
<style>
    .stat-card { border:1px solid var(--bs-border-color); border-radius:14px; padding:1rem 1.25rem; }
    .stat-card .v { font-size:1.7rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .stat-card .l { font-size:.8rem; color:var(--bs-secondary-color); }
    .bar-row { display:flex; align-items:center; gap:.75rem; margin-bottom:.6rem; }
    .bar-row .name { width:160px; flex:0 0 auto; font-size:.85rem; }
    .bar-track { flex:1 1 auto; background:var(--bs-secondary-bg); border-radius:999px; height:14px; overflow:hidden; }
    .bar-fill { height:100%; border-radius:999px; background:linear-gradient(90deg,#0a9d5a,#A8CF45); min-width:2px; }
    .bar-row .num { width:42px; text-align:right; font-weight:600; font-size:.85rem; }
    .ring { --p:0; width:120px; height:120px; border-radius:50%;
        background:conic-gradient(#068A4F calc(var(--p)*1%), var(--bs-secondary-bg) 0);
        display:flex; align-items:center; justify-content:center; }
    .ring > div { width:88px; height:88px; border-radius:50%; background:var(--bs-body-bg); display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .ring .pct { font-size:1.5rem; font-weight:700; font-family:'Rajdhani',sans-serif; }

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
        /* Faz as barras/anéis/badges imprimirem com cor */
        .bar-fill, .bar-track, .badge, .ring, [class*="bg-"] {
            -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important;
        }
        .bar-track { background:#e9ecef !important; }
        a[href]::after { content:''; } /* não anexa URLs */
        @page { margin:1.2cm; }
    }
</style>
@endpush

@section('content')
{{-- Cabeçalho visível só no PDF/impressão --}}
<div class="report-print-head">
    <div class="d-flex justify-content-between align-items-end">
        <div>
            <div class="brand">FOURLINE <span>CONNECT</span></div>
            <div style="font-size:1.05rem; font-weight:700;">Relatório de Atendimento</div>
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
        <p class="text-secondary small mb-0">Visão gerencial do atendimento (dados do GLPI).</p>
    </div>
    <button type="button" class="btn btn-success no-print" onclick="window.print()">
        <i class="bi bi-file-earmark-pdf me-1"></i> Imprimir / Salvar PDF
    </button>
</div>

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
@endsection
