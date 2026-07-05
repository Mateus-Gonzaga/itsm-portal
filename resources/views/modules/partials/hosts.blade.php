<div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Host</th><th>Status</th><th class="text-center">CPU</th><th class="text-center">RAM</th><th class="text-center">Disco</th></tr></thead>
        <tbody>
            @forelse ($hosts as $h)
                <tr class="host-row" style="cursor:pointer" onclick="openHost(this)"
                    data-id="{{ $h['id'] ?? '' }}" data-name="{{ $h['name'] }}"
                    data-cpu="{{ $h['cpu'] ?? '' }}" data-ram="{{ $h['ram'] ?? '' }}" data-disk="{{ $h['disk'] ?? '' }}"
                    title="Ver detalhes e histórico">
                    <td class="fw-semibold">{{ $h['name'] }} <i class="bi bi-graph-up-arrow text-secondary small ms-1"></i></td>
                    <td class="text-nowrap">{!! $avail($h['available']) !!}</td>
                    <td class="text-center">{!! $metric($h['cpu']) !!}</td>
                    <td class="text-center">{!! $metric($h['ram']) !!}</td>
                    <td class="text-center">{!! $metric($h['disk']) !!}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Nenhum host neste grupo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
