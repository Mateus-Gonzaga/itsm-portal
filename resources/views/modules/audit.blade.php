@extends('layouts.app')

@section('title', 'Auditoria')

@section('content')
<x-page-header icon="bi-shield-lock" title="Auditoria"
    subtitle="Registro das ações sensíveis (diretório, inventário, base de conhecimento).">
    <form method="GET" class="input-group input-group-sm" style="max-width:320px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por ação, usuário, descrição...">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
    </form>
</x-page-header>

@if ($logs->isEmpty())
    <div class="card">
        @if ($q !== '' && $q !== null)
            <x-empty-state icon="bi-search" title="Nada encontrado" :text="'Nenhum registro corresponde a “'.$q.'”. Tente outro termo.'">
                <a href="{{ route('modules.audit') }}" class="btn btn-sm btn-outline-secondary">Limpar busca</a>
            </x-empty-state>
        @else
            <x-empty-state icon="bi-shield-check" title="Nenhum registro de auditoria ainda"
                text="As ações sensíveis (criar, editar, mover ou excluir ativos, cadastros e registros) aparecem aqui com usuário, data e IP." />
        @endif
    </div>
@else
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle table-cards">
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
            @foreach ($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td class="small">{{ $log->user_name ?? '—' }}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace">{{ $log->action }}</span></td>
                    <td class="small">{{ $log->description }}</td>
                    <td class="small text-muted font-monospace">{{ $log->ip }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{ $logs->links() }}
@endif
@endsection
