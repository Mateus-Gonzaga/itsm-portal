@php $hid = 'h'.substr(md5(spl_object_hash($hosts)), 0, 6); @endphp
@if ($hosts->count() > 6)
    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
        <div class="input-group input-group-sm" style="max-width:240px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" placeholder="Buscar host…" aria-label="Buscar host"
                   oninput="filterHosts('{{ $hid }}', this.value)">
        </div>
        <span class="small text-secondary">Clique no título da coluna para ordenar.</span>
    </div>
@endif
<div class="table-wrap">
    <table class="table table-hover align-middle mb-0" id="{{ $hid }}">
        <thead>
            <tr>
                <th role="button" onclick="sortHosts('{{ $hid }}', 0, 'text')">Host <i class="bi bi-arrow-down-up small text-secondary"></i></th>
                <th role="button" onclick="sortHosts('{{ $hid }}', 1, 'text')">Status <i class="bi bi-arrow-down-up small text-secondary"></i></th>
                <th class="text-center" role="button" onclick="sortHosts('{{ $hid }}', 2, 'num')">CPU <i class="bi bi-arrow-down-up small text-secondary"></i></th>
                <th class="text-center" role="button" onclick="sortHosts('{{ $hid }}', 3, 'num')">RAM <i class="bi bi-arrow-down-up small text-secondary"></i></th>
                <th class="text-center" role="button" onclick="sortHosts('{{ $hid }}', 4, 'num')">Disco <i class="bi bi-arrow-down-up small text-secondary"></i></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($hosts as $h)
                @php
                    // Sem agente comunicando: 0% não é medição real — mostra "—".
                    $semDados = ($h['webOnly'] ?? false) || (($h['available'] ?? 0) !== 1 && ! ($h['cpu'] || $h['ram'] || $h['disk']));
                    $cpu = $semDados ? null : $h['cpu'];
                    $ram = $semDados ? null : $h['ram'];
                    $disk = $semDados ? null : $h['disk'];
                    // Valores para ordenar as colunas (status: indisponível < disponível < sem agente < web).
                    $statusOrdem = ($h['webOnly'] ?? false) ? 3 : (($h['available'] ?? 0) === 2 ? 0 : (($h['available'] ?? 0) === 1 ? 1 : 2));
                    $sortData = json_encode([mb_strtolower($h['name']), $statusOrdem, $cpu ?? -1, $ram ?? -1, $disk ?? -1]);
                @endphp
                <tr class="host-row" style="cursor:pointer" onclick="openHost(this)"
                    data-id="{{ $h['id'] ?? '' }}" data-name="{{ $h['name'] }}"
                    data-cpu="{{ $cpu ?? '' }}" data-ram="{{ $ram ?? '' }}" data-disk="{{ $disk ?? '' }}"
                    data-sort="{{ $sortData }}"
                    title="Ver detalhes e histórico">
                    <td class="fw-semibold">{{ $h['name'] }} <i class="bi bi-graph-up-arrow text-secondary small ms-1"></i></td>
                    <td class="text-nowrap">{!! $avail($h['available'], $h['webOnly'] ?? false) !!}</td>
                    <td class="text-center">{!! $metric($cpu) !!}</td>
                    <td class="text-center">{!! $metric($ram) !!}</td>
                    <td class="text-center">{!! $metric($disk) !!}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Nenhum host neste grupo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@once
<script>
    // Busca e ordenação das tabelas de hosts (sem recarregar a página).
    function filterHosts(id, term) {
        term = (term || '').toLowerCase().trim();
        document.querySelectorAll('#' + id + ' tbody .host-row').forEach(function (tr) {
            tr.classList.toggle('d-none', term !== '' && tr.dataset.name.toLowerCase().indexOf(term) === -1);
        });
    }
    function sortHosts(id, col, type) {
        var table = document.getElementById(id), tbody = table.querySelector('tbody');
        var dir = table.dataset.sortCol == col && table.dataset.sortDir === 'asc' ? 'desc' : (type === 'num' ? 'desc' : 'asc');
        table.dataset.sortCol = col; table.dataset.sortDir = dir;
        Array.from(tbody.querySelectorAll('.host-row')).sort(function (a, b) {
            var va = JSON.parse(a.dataset.sort)[col], vb = JSON.parse(b.dataset.sort)[col];
            var r = va < vb ? -1 : (va > vb ? 1 : 0);
            return dir === 'asc' ? r : -r;
        }).forEach(function (tr) { tbody.appendChild(tr); });
    }
</script>
@endonce
