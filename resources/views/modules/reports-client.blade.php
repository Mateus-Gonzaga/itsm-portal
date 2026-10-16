<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de atendimento — {{ $f['cliente'] }} — FOURLINE</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 32px 40px; font-size: 13px; }
        .toolbar { margin-bottom: 20px; display: flex; gap: 8px; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 8px; border: 1px solid #067a45; background: #0a9d5a; color: #fff; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn.sec { background: #fff; color: #067a45; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0a9d5a; padding-bottom: 12px; margin-bottom: 18px; }
        header .brand { font-size: 22px; font-weight: 800; color: #067a45; letter-spacing: .5px; }
        header .brand small { display: block; font-size: 12px; font-weight: 500; color: #666; letter-spacing: normal; }
        header .meta { text-align: right; font-size: 12px; color: #444; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 14px; margin: 22px 0 8px; color: #05502e; }
        .sub { color: #555; margin: 0 0 16px; }
        .kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 8px; }
        .kpi { border: 1px solid #d9e6dd; border-radius: 10px; padding: 10px 12px; background: #f6fbf7; }
        .kpi .v { font-size: 20px; font-weight: 800; color: #05502e; }
        .kpi .l { font-size: 11px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #cfcfcf; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #eef7f0; color: #05502e; font-size: 10.5px; text-transform: uppercase; letter-spacing: .3px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #fafafa; }
        .ok { color: #067a45; font-weight: 600; } .bad { color: #b42318; font-weight: 600; } .muted { color: #777; }
        .two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .note { font-size: 11px; color: #555; margin-top: 14px; }
        footer { margin-top: 32px; font-size: 10px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
        @media (max-width: 800px) { .kpis { grid-template-columns: repeat(2, 1fr); } .two { grid-template-columns: 1fr; } body { padding: 16px; } }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            @page { margin: 16mm 12mm; }
            th, .kpi { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
        <a class="btn sec" href="{{ route('modules.reports', ['aba' => 'clientes']) }}">← Voltar aos relatórios</a>
    </div>

    <header>
        <div class="brand">FOURLINE<small>Connect · Relatório de Atendimento</small></div>
        <div class="meta">Gerado em {{ now()->format('d/m/Y H:i') }}<br>por {{ $geradoPor }}</div>
    </header>

    <h1>{{ $f['cliente'] }}</h1>
    <p class="sub">
        Período: {{ $f['periodo'] === 'tudo' ? 'todo o histórico' : $f['de']->format('d/m/Y').' a '.$f['ate']->format('d/m/Y') }}
        @if ($f['tecnico']) · Técnico: {{ $f['tecnico'] }}@endif
        @if ($f['categoria']) · Categoria: {{ $f['categoria'] }}@endif
    </p>

    <div class="kpis">
        <div class="kpi"><div class="v">{{ $kpi['total'] }}</div><div class="l">Chamados abertos no período</div></div>
        <div class="kpi"><div class="v">{{ $kpi['resolvidos'] }}</div><div class="l">Resolvidos ({{ $kpi['resolucao'] }}%)</div></div>
        <div class="kpi"><div class="v">{{ $kpi['abertos'] }}</div><div class="l">Em andamento</div></div>
        <div class="kpi"><div class="v">{{ $kpi['tempo'] }}</div><div class="l">Tempo médio de solução</div></div>
        <div class="kpi"><div class="v">{{ $kpi['sla'] === null ? '—' : $kpi['sla'].'%' }}</div><div class="l">Resolvidos dentro do prazo</div></div>
        <div class="kpi"><div class="v">{{ $kpi['atrasados'] }}</div><div class="l">Em atraso</div></div>
    </div>

    <div class="two">
        <div>
            <h2>Por unidade / filial</h2>
            <table>
                <thead><tr><th>Unidade</th><th class="num">Chamados</th><th class="num">Resolvidos</th><th class="num">Tempo médio</th></tr></thead>
                <tbody>
                    @forelse ($porFilial as $r)
                        <tr><td>{{ $r['nome'] }}</td><td class="num">{{ $r['total'] }}</td><td class="num">{{ $r['resolvidos'] }}</td><td class="num">{{ $r['tempo'] }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sem chamados no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>
            <h2>Por tipo de demanda</h2>
            <table>
                <thead><tr><th>Categoria</th><th class="num">Chamados</th><th class="num">Resolvidos</th><th class="num">Tempo médio</th></tr></thead>
                <tbody>
                    @forelse ($porCategoria as $r)
                        <tr><td>{{ $r['nome'] }}</td><td class="num">{{ $r['total'] }}</td><td class="num">{{ $r['resolvidos'] }}</td><td class="num">{{ $r['tempo'] }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sem chamados no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2>Chamados do período</h2>
    <table>
        <thead>
            <tr><th style="width:52px">Nº</th><th>Assunto</th><th>Unidade</th><th>Solicitante</th><th>Situação</th><th class="num">Aberto em</th><th class="num">Solucionado</th><th>Prazo</th></tr>
        </thead>
        <tbody>
            @forelse ($chamados as $t)
                @php $s = $slaLabel($t); @endphp
                <tr>
                    <td class="num">#{{ $t->id }}</td>
                    <td>{{ $t->title }}</td>
                    <td>{{ $filial($t) ?: '—' }}</td>
                    <td>{{ $t->requesterName }}</td>
                    <td>{{ $t->status->label() }}</td>
                    <td class="num">{{ $t->createdAt->format('d/m/Y H:i') }}</td>
                    <td class="num">{{ $t->solvedAt?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="{{ in_array($s, ['No prazo'], true) ? 'ok' : (in_array($s, ['Fora do prazo', 'Atrasado'], true) ? 'bad' : 'muted') }}">{{ $s }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted" style="text-align:center;padding:20px">Nenhum chamado no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">
        "Tempo médio de solução" considera do registro do chamado até a solução. "Resolvidos dentro do prazo" considera os chamados
        resolvidos que tinham prazo de atendimento (SLA) definido.
    </p>

    <footer>FOURLINE Connect — documento gerado eletronicamente em {{ now()->format('d/m/Y H:i') }}.</footer>
</body>
</html>
