@extends('layouts.app')

@section('title', 'Auditoria — FOURLINE Connect')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Auditoria</h1>
        <p class="text-secondary small mb-0">Registro das ações sensíveis (diretório, inventário, base de conhecimento).</p>
    </div>
    <form method="GET" class="input-group input-group-sm" style="max-width:320px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por ação, usuário, descrição...">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead>
            <tr class="text-secondary small">
                <th>Quando</th>
                <th>Usuário</th>
                <th>Ação</th>
                <th>Descrição</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td class="small">{{ $log->user_name ?? '—' }}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace">{{ $log->action }}</span></td>
                    <td class="small">{{ $log->description }}</td>
                    <td class="small text-muted font-monospace">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-shield-check d-block fs-1 mb-2 opacity-50"></i>Nenhum registro de auditoria ainda.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $logs->links() }}
@endsection
