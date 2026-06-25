<div class="table-wrap">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Severidade</th><th>Host</th><th>Problema</th><th>Desde</th></tr></thead>
        <tbody>
            @forelse ($problems as $p)
                <tr>
                    <td><span class="badge bg-{{ $p['color'] }}">{{ $p['severityLabel'] }}</span></td>
                    <td class="text-nowrap">{{ $p['host'] }}</td>
                    <td>{{ $p['name'] }}</td>
                    <td class="small text-secondary text-nowrap">{{ $p['since']->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-check2-circle text-success me-1"></i> Nenhum problema ativo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
