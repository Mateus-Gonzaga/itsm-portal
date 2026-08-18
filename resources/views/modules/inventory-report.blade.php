<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de Inventário — FOURLINE</title>
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
        .sub { color: #555; margin: 0 0 16px; }
        .info { display: flex; gap: 28px; flex-wrap: wrap; margin-bottom: 14px; font-size: 12px; }
        .info b { color: #067a45; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #cfcfcf; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #eef7f0; color: #05502e; font-size: 11px; text-transform: uppercase; letter-spacing: .3px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #fafafa; }
        tfoot td { font-weight: 700; background: #eef7f0; }
        .tag { font-family: 'Consolas', monospace; }
        .sign { display: flex; gap: 60px; margin-top: 64px; page-break-inside: avoid; }
        .sign .box { flex: 1; text-align: center; }
        .sign .line { border-top: 1px solid #333; margin-bottom: 6px; padding-top: 6px; }
        .sign .role { font-weight: 700; }
        .sign .hint { font-size: 11px; color: #666; }
        footer { margin-top: 40px; font-size: 10px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            @page { margin: 18mm 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
        <a class="btn sec" href="{{ route('modules.inventory') }}">← Voltar ao inventário</a>
    </div>

    <header>
        <div class="brand">FOURLINE<small>Connect · Relatório de Inventário</small></div>
        <div class="meta">
            Gerado em {{ $geradoEm->format('d/m/Y H:i') }}<br>
            por {{ $geradoPor }}
        </div>
    </header>

    <h1>Relatório de Inventário de Ativos</h1>
    <p class="sub">{{ $entidade !== '' ? 'Cliente / entidade: '.$entidade : 'Todas as entidades' }}</p>

    <div class="info">
        <div><b>Total de itens:</b> {{ $assets->count() }}</div>
        <div><b>Valor total estimado:</b> R$ {{ number_format($valorTotal, 2, ',', '.') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:34px">#</th>
                <th>Pat.</th>
                <th>Tipo</th>
                <th>Nome / equipamento</th>
                @if ($entidade === '')<th>Entidade</th>@endif
                <th>Modelo</th>
                <th>Nº de série</th>
                <th class="num">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assets as $i => $a)
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td class="tag">{{ $a['tag'] ?: '—' }}</td>
                    <td>{{ $a['type'] }}</td>
                    <td>{{ $a['name'] }}</td>
                    @if ($entidade === '')<td>{{ $a['entity'] }}</td>@endif
                    <td>{{ $a['model'] }}</td>
                    <td>{{ $a['serial'] }}</td>
                    <td class="num">{{ ! empty($a['value']) ? number_format($a['value'], 2, ',', '.') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $entidade === '' ? 8 : 7 }}" style="text-align:center;color:#999;padding:24px">Nenhum ativo para esta seleção.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $entidade === '' ? 7 : 6 }}" class="num">Total</td>
                <td class="num">R$ {{ number_format($valorTotal, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <p style="font-size:11px;color:#555;margin-top:18px">
        Declaramos que os equipamentos relacionados acima conferem com o inventário físico da unidade na data de emissão deste documento.<br>
        O valor estimado ao equipamento é considerado a época da aquisição.
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
            <div class="hint">Nome / assinatura {{ $entidade !== '' ? '· '.$entidade : '' }}</div>
        </div>
    </div>

    <footer>
        FOURLINE Connect — documento gerado eletronicamente em {{ $geradoEm->format('d/m/Y H:i') }}.
    </footer>
</body>
</html>
