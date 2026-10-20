<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de Atendimento · {{ $f['cliente'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 32px 40px; font-size: 13px; }
        .toolbar { margin-bottom: 20px; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 8px; border: 1px solid #067a45; background: #0a9d5a; color: #fff; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn.sec { background: #fff; color: #067a45; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0a9d5a; padding-bottom: 12px; margin-bottom: 18px; }
        header .brand { font-size: 22px; font-weight: 800; color: #067a45; letter-spacing: .5px; }
        header .brand small { display: block; font-size: 12px; font-weight: 500; color: #666; letter-spacing: normal; }
        header .meta { text-align: right; font-size: 12px; color: #444; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #05502e; text-transform: uppercase; letter-spacing: .3px; }
        .sub { color: #555; margin: 0 0 16px; }
        .info { display: flex; gap: 28px; flex-wrap: wrap; margin-bottom: 14px; font-size: 12px; }
        .info b { color: #067a45; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #cfcfcf; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #eef7f0; color: #05502e; font-size: 11px; text-transform: uppercase; letter-spacing: .3px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #fafafa; }
        tfoot td { font-weight: 700; background: #eef7f0; }
        .resumo { display: flex; gap: 20px; }
        .resumo > div { flex: 1; min-width: 0; }
        .sign { display: flex; gap: 60px; margin-top: 64px; page-break-inside: avoid; }
        .sign .box { flex: 1; text-align: center; }
        .sign .line { border-top: 1px solid #333; margin-bottom: 6px; padding-top: 6px; }
        .sign .role { font-weight: 700; }
        .sign .hint { font-size: 11px; color: #666; }
        footer { margin-top: 40px; font-size: 10px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
        @media (max-width: 800px) { body { padding: 16px; } .resumo { flex-direction: column; } .sign { gap: 24px; } }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            @page { margin: 18mm 14mm; }
            th, tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
        <div class="meta">
            Gerado em {{ now()->format('d/m/Y H:i') }}<br>
            por {{ $geradoPor }}
        </div>
    </header>

    <h1>Relatório de Atendimento</h1>
    <p class="sub">
        Cliente: {{ $f['cliente'] }} ·
        Período: {{ $f['periodo'] === 'tudo' ? 'todo o histórico' : $f['de']->format('d/m/Y').' a '.$f['ate']->format('d/m/Y') }}
        @if ($f['tecnico']) · Técnico: {{ $f['tecnico'] }}@endif
        @if ($f['categoria']) · Categoria: {{ $f['categoria'] }}@endif
    </p>

    <div class="info">
        <div><b>Chamados no período:</b> {{ $kpi['total'] }}</div>
        <div><b>Resolvidos:</b> {{ $kpi['resolvidos'] }} ({{ $kpi['resolucao'] }}%)</div>
        <div><b>Em andamento:</b> {{ $kpi['abertos'] }}</div>
        <div><b>Tempo médio de solução:</b> {{ $kpi['tempo'] }}</div>
        @if ($kpi['sla'] !== null)<div><b>Resolvidos no prazo:</b> {{ $kpi['sla'] }}%</div>@endif
        @if ($kpi['atrasados'] > 0)<div><b>Em atraso:</b> {{ $kpi['atrasados'] }}</div>@endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:34px">#</th>
                <th style="width:60px">Nº</th>
                <th>Assunto</th>
                <th>Unidade</th>
                <th>Solicitante</th>
                <th>Situação</th>
                <th class="num">Aberto em</th>
                <th class="num">Solucionado em</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($chamados as $i => $t)
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>#{{ $t->id }}</td>
                    <td>{{ $t->title }}</td>
                    <td>{{ $filial($t) ?: '—' }}</td>
                    <td>{{ $t->requesterName }}</td>
                    <td>{{ $t->status->label() }}</td>
                    <td class="num">{{ $t->createdAt->format('d/m/Y H:i') }}</td>
                    <td class="num">{{ $t->solvedAt?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#999;padding:24px">Nenhum chamado no período.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="num">Total de chamados</td>
                <td class="num">{{ $kpi['total'] }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($kpi['total'] > 0)
        <div class="resumo">
            <div>
                <h2>Resumo por unidade</h2>
                <table>
                    <thead><tr><th>Unidade</th><th class="num">Chamados</th><th class="num">Resolvidos</th><th class="num">Tempo médio</th></tr></thead>
                    <tbody>
                        @foreach ($porFilial as $r)
                            <tr><td>{{ $r['nome'] }}</td><td class="num">{{ $r['total'] }}</td><td class="num">{{ $r['resolvidos'] }}</td><td class="num">{{ $r['tempo'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>
                <h2>Resumo por tipo de demanda</h2>
                <table>
                    <thead><tr><th>Categoria</th><th class="num">Chamados</th><th class="num">Resolvidos</th><th class="num">Tempo médio</th></tr></thead>
                    <tbody>
                        @foreach ($porCategoria as $r)
                            <tr><td>{{ $r['nome'] }}</td><td class="num">{{ $r['total'] }}</td><td class="num">{{ $r['resolvidos'] }}</td><td class="num">{{ $r['tempo'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p style="font-size:11px;color:#555;margin-top:18px">
        Declaramos que os atendimentos relacionados acima foram registrados na central de chamados da FOURLINE no período informado.<br>
        O tempo de solução é contado do registro do chamado até a sua solução.
    </p>

    <div class="sign">
        <div class="box">
            <div class="line"></div>
            <div class="role">FOURLINE</div>
            <div class="hint">Responsável técnico · {{ $geradoPor }}</div>
        </div>
        <div class="box">
            <div class="line"></div>
            <div class="role">Cliente</div>
            <div class="hint">Nome / assinatura · {{ $f['cliente'] }}</div>
        </div>
    </div>

    <footer>
        FOURLINE Connect — documento gerado eletronicamente em {{ now()->format('d/m/Y H:i') }}.
    </footer>
</body>
</html>
