@extends('layouts.app')

@section('title', 'Técnicos — FOURLINE Connect')

@push('head')
<style>
    .stat-card { border:1px solid var(--bs-border-color); border-radius:14px; padding:1rem 1.25rem; display:flex; align-items:center; gap:.9rem; }
    .stat-card .ic { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; color:#fff; background:linear-gradient(135deg,#0a9d5a,#067a45); flex:0 0 auto; }
    .stat-card .v { font-size:1.5rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .stat-card .l { font-size:.8rem; color:var(--bs-secondary-color); }
    .tech-avatar { width:36px; height:36px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:#033d22; background:#A8CF45; flex:0 0 auto; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Técnicos</h1>
        <p class="text-secondary small mb-0">Equipe de atendimento e carga de trabalho (dados do GLPI).</p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openTech()"><i class="bi bi-person-plus me-1"></i> Adicionar técnico</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-3 col-6"><div class="stat-card"><div class="ic"><i class="bi bi-person-badge"></i></div><div><div class="v">{{ $stats['tecnicos'] }}</div><div class="l">Técnicos</div></div></div></div>
    <div class="col-sm-3 col-6"><div class="stat-card"><div class="ic"><i class="bi bi-ticket-detailed"></i></div><div><div class="v">{{ $stats['atribuidos'] }}</div><div class="l">Chamados atribuídos</div></div></div></div>
    <div class="col-sm-3 col-6"><div class="stat-card"><div class="ic"><i class="bi bi-hourglass-split"></i></div><div><div class="v">{{ $stats['abertos'] }}</div><div class="l">Em atendimento</div></div></div></div>
    <div class="col-sm-3 col-6"><div class="stat-card"><div class="ic"><i class="bi bi-calendar-check"></i></div><div><div class="v">{{ $stats['agendados'] }}</div><div class="l">Agendamentos futuros</div></div></div></div>
</div>

<div class="card">
    <div class="card-header bg-transparent fw-semibold"><i class="bi bi-people me-1"></i> Equipe</div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Técnico</th><th>Escopo</th>
                        <th class="text-center">Atribuídos</th><th class="text-center">Em aberto</th>
                        <th class="text-center">Resolvidos</th><th class="text-center">Agenda</th>
                        <th class="text-center">Status</th><th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="tech-avatar">{{ mb_strtoupper(mb_substr($r['name'], 0, 1)) }}</span>
                                    <span class="fw-semibold">{{ $r['name'] }}</span>
                                </div>
                            </td>
                            <td class="small text-secondary">{{ $r['entity'] }} @if ($r['recursive'])<span class="badge bg-success-subtle text-success-emphasis ms-1">+ sub</span>@endif</td>
                            <td class="text-center">{{ $r['atribuidos'] }}</td>
                            <td class="text-center">@if ($r['abertos'] > 0)<span class="badge bg-warning-subtle text-warning-emphasis">{{ $r['abertos'] }}</span>@else<span class="text-muted">0</span>@endif</td>
                            <td class="text-center">@if ($r['resolvidos'] > 0)<span class="badge bg-success-subtle text-success-emphasis">{{ $r['resolvidos'] }}</span>@else<span class="text-muted">0</span>@endif</td>
                            <td class="text-center">@if ($r['agendados'] > 0)<span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-calendar-event me-1"></i>{{ $r['agendados'] }}</span>@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-center">@if ($r['active'])<span class="badge bg-success-subtle text-success-emphasis">Ativo</span>@else<span class="badge bg-secondary-subtle text-secondary-emphasis">Inativo</span>@endif</td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('directory.users.toggle', $r['id']) }}" class="d-inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="active" value="{{ $r['active'] ? 0 : 1 }}">
                                    <button class="btn btn-sm btn-outline-{{ $r['active'] ? 'warning' : 'success' }}" title="{{ $r['active'] ? 'Desativar' : 'Ativar' }}"><i class="bi bi-{{ $r['active'] ? 'pause-fill' : 'play-fill' }}"></i></button>
                                </form>
                                <button class="btn btn-sm btn-outline-secondary" onclick="editTech(this)"
                                        data-id="{{ $r['id'] }}" data-login="{{ $r['login'] }}" data-name="{{ $r['name'] }}"
                                        data-entity="{{ $r['entity_id'] }}" data-recursive="{{ $r['recursive'] ? 1 : 0 }}" data-active="{{ $r['active'] ? 1 : 0 }}"><i class="bi bi-pencil"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum técnico (perfil "Técnico FL"). Use "Adicionar técnico".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal técnico --}}
<div class="modal fade" id="techModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="techForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="t_method" value="POST">
                <input type="hidden" name="profile_id" value="{{ $tecnicoProfileId }}">
                <div class="modal-header"><h5 class="modal-title" id="t_title">Adicionar técnico</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Login</label><input type="text" name="login" id="t_login" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Nome</label><input type="text" name="name" id="t_name" class="form-control" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Entidade de atuação</label>
                        <select name="entity_id" id="t_entity" class="form-select">
                            @foreach ($entities as $e)<option value="{{ $e['id'] }}" @selected($e['level'] === 2)>{{ $e['completename'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Senha <span class="text-muted small" id="t_pwhint"></span></label><input type="text" name="password" id="t_password" class="form-control" autocomplete="off"></div>
                    <div class="d-flex gap-4">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="recursive" value="1" id="t_recursive" checked><label class="form-check-label" for="t_recursive">Recursivo (subentidades)</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="t_active" checked><label class="form-check-label" for="t_active">Ativo</label></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const STORE = "{{ route('directory.users.store') }}";
    const UPD = "{{ url('diretorio/usuarios') }}";
    const modal = new bootstrap.Modal(document.getElementById('techModal'));
    const $ = (id) => document.getElementById(id);

    window.openTech = function () {
        $('t_title').textContent = 'Adicionar técnico';
        $('techForm').action = STORE; $('t_method').value = 'POST';
        $('t_login').value = ''; $('t_login').readOnly = false; $('t_name').value = '';
        $('t_password').required = true; $('t_pwhint').textContent = '(mínimo 6 caracteres)';
        $('t_recursive').checked = true; $('t_active').checked = true;
        modal.show();
    };
    window.editTech = function (btn) {
        $('t_title').textContent = 'Editar técnico';
        $('techForm').action = UPD + '/' + btn.dataset.id; $('t_method').value = 'PUT';
        $('t_login').value = btn.dataset.login; $('t_login').readOnly = true;
        $('t_name').value = btn.dataset.name; $('t_entity').value = btn.dataset.entity;
        $('t_recursive').checked = btn.dataset.recursive === '1'; $('t_active').checked = btn.dataset.active === '1';
        $('t_password').value = ''; $('t_password').required = false; $('t_pwhint').textContent = '(deixe em branco para manter)';
        modal.show();
    };
})();
</script>
@endpush
