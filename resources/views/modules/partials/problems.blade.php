@php
    // Lista de problemas do Zabbix com busca, filtro de severidade, agrupamento de
    // problemas iguais e "ruído" escondido por padrão. $pid separa várias listas na página.
    $pid = $pid ?? 'p';
    $cliente = $cliente ?? null;
    $noiseCount = $problems->where('noise', true)->count();
    $groups = $problems
        ->groupBy(fn ($p) => $p['severity'].'|'.$p['name'])
        ->map(fn ($g) => $g->first() + [
            'hosts' => $g->pluck('host')->unique()->values(),
            'oldest' => $g->min('since'),
        ])
        ->sortBy([['severity', 'desc'], fn ($a, $b) => $b['hosts']->count() <=> $a['hosts']->count()])
        ->values();

    // Link para abrir chamado já preenchido com o alerta.
    $ticketUrl = function (array $p, $hosts) use ($cliente) {
        $hosts = collect($hosts);
        $alvo = $hosts->count() === 1 ? $hosts->first() : $hosts->count().' hosts';
        $desc = "Alerta do monitoramento (Zabbix)\n"
            .($cliente ? "Cliente: {$cliente}\n" : '')
            ."Severidade: {$p['severityLabel']}\n"
            ."Problema: {$p['name']}\n"
            .'Host(s): '.$hosts->implode(', ')."\n"
            .'Desde: '.($p['oldest'] ?? $p['since'])->format('d/m/Y H:i');

        return route('tickets.create', [
            'titulo' => \Illuminate\Support\Str::limit("[{$alvo}] {$p['name']}", 200, '…'),
            'descricao' => \Illuminate\Support\Str::limit($desc, 1800, '…'),
        ]);
    };
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="pf-{{ $pid }}">
    <div class="input-group input-group-sm" style="max-width:260px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control pf-q" placeholder="Buscar host ou problema…" aria-label="Buscar problema">
    </div>
    <select class="form-select form-select-sm pf-sev" style="width:auto" aria-label="Severidade mínima">
        <option value="0">Todas as severidades</option>
        <option value="4">Alta ou mais</option>
        <option value="3">Média ou mais</option>
        <option value="2">Atenção ou mais</option>
    </select>
    <div class="form-check form-switch mb-0 small">
        <input class="form-check-input pf-group" type="checkbox" id="pfg-{{ $pid }}" checked>
        <label class="form-check-label" for="pfg-{{ $pid }}">Agrupar iguais</label>
    </div>
    @if ($noiseCount > 0)
        <div class="form-check form-switch mb-0 small">
            <input class="form-check-input pf-noise" type="checkbox" id="pfn-{{ $pid }}">
            <label class="form-check-label text-secondary" for="pfn-{{ $pid }}" title="Avisos sem importância, como o serviço GoogleUpdater parado">Mostrar ruído ({{ $noiseCount }})</label>
        </div>
    @endif
    <span class="ms-auto small text-secondary pf-count"></span>
</div>

<div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Severidade</th><th>Problema</th><th>Host</th><th class="text-nowrap">Desde</th><th class="text-end"><span class="visually-hidden">Ações</span></th></tr></thead>
        {{-- Visão agrupada --}}
        <tbody class="pf-grouped">
            @foreach ($groups as $g)
                <tr class="pf-row" data-sev="{{ $g['severity'] }}" data-noise="{{ ($g['noise'] ?? false) ? 1 : 0 }}"
                    data-text="{{ mb_strtolower($g['name'].' '.$g['hosts']->implode(' ')) }}">
                    <td><span class="badge bg-{{ $g['color'] }}">{{ $g['severityLabel'] }}</span></td>
                    <td>{{ $g['name'] }} @if ($g['noise'] ?? false)<span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">ruído</span>@endif</td>
                    <td class="text-nowrap">
                        @if ($g['hosts']->count() === 1)
                            {{ $g['hosts']->first() }}
                        @else
                            <details>
                                <summary class="text-primary" style="cursor:pointer">{{ $g['hosts']->count() }} hosts</summary>
                                <div class="small text-secondary text-wrap" style="max-width:280px">{{ $g['hosts']->implode(', ') }}</div>
                            </details>
                        @endif
                    </td>
                    <td class="small text-secondary text-nowrap">{{ $g['oldest']->diffForHumans() }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ $ticketUrl($g, $g['hosts']) }}" class="btn btn-sm btn-outline-success" title="Abrir chamado com este alerta"><i class="bi bi-plus-circle"></i><span class="d-none d-xl-inline ms-1">Chamado</span></a>
                    </td>
                </tr>
            @endforeach
        </tbody>
        {{-- Visão individual (um por host) --}}
        <tbody class="pf-list d-none">
            @foreach ($problems as $p)
                <tr class="pf-row" data-sev="{{ $p['severity'] }}" data-noise="{{ ($p['noise'] ?? false) ? 1 : 0 }}"
                    data-text="{{ mb_strtolower($p['name'].' '.$p['host']) }}">
                    <td><span class="badge bg-{{ $p['color'] }}">{{ $p['severityLabel'] }}</span></td>
                    <td>{{ $p['name'] }}</td>
                    <td class="text-nowrap">{{ $p['host'] }}</td>
                    <td class="small text-secondary text-nowrap">{{ $p['since']->diffForHumans() }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ $ticketUrl($p + ['oldest' => $p['since']], [$p['host']]) }}" class="btn btn-sm btn-outline-success" title="Abrir chamado com este alerta"><i class="bi bi-plus-circle"></i></a>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tbody class="pf-empty d-none">
            <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-check2-circle text-success me-1"></i> Nenhum problema com estes filtros.</td></tr>
        </tbody>
    </table>
</div>

<script>
(function () {
    var box = document.getElementById('pf-{{ $pid }}');
    if (!box) return;
    var root = box.parentElement;
    var q = box.querySelector('.pf-q'), sev = box.querySelector('.pf-sev'),
        grp = box.querySelector('.pf-group'), noise = box.querySelector('.pf-noise'), count = box.querySelector('.pf-count');
    var KEY = 'zbx-filtros-{{ $pid }}';

    // Lembra os filtros entre as atualizações automáticas da página.
    try {
        var s = JSON.parse(sessionStorage.getItem(KEY) || '{}');
        if (s.q) q.value = s.q;
        if (s.sev) sev.value = s.sev;
        if (s.grp === false) grp.checked = false;
        if (noise && s.noise) noise.checked = true;
    } catch (e) {}

    function apply() {
        var term = q.value.toLowerCase().trim(), min = +sev.value, showNoise = noise ? noise.checked : false;
        var grouped = grp.checked, visible = 0;
        root.querySelector('.pf-grouped').classList.toggle('d-none', !grouped);
        root.querySelector('.pf-list').classList.toggle('d-none', grouped);
        root.querySelectorAll(grouped ? '.pf-grouped .pf-row' : '.pf-list .pf-row').forEach(function (tr) {
            var ok = (+tr.dataset.sev >= min) && (showNoise || tr.dataset.noise !== '1') && (!term || tr.dataset.text.indexOf(term) !== -1);
            tr.classList.toggle('d-none', !ok);
            if (ok) visible++;
        });
        root.querySelector('.pf-empty').classList.toggle('d-none', visible > 0);
        count.textContent = visible + (grouped ? ' tipo(s) de problema' : ' problema(s)');
        try { sessionStorage.setItem(KEY, JSON.stringify({ q: q.value, sev: sev.value, grp: grouped, noise: noise ? noise.checked : false })); } catch (e) {}
    }
    [q, sev, grp, noise].forEach(function (el) { if (el) el.addEventListener(el === q ? 'input' : 'change', apply); });
    apply();
})();
</script>
