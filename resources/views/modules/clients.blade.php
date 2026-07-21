@extends('layouts.app')

@section('title', 'Clientes — FOURLINE Connect')

@push('head')
<style>
    .stat-card { border:1px solid var(--bs-border-color); border-radius:14px; padding:1rem 1.25rem; display:flex; align-items:center; gap:.9rem; }
    .stat-card .ic { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; color:#fff; background:linear-gradient(135deg,#0a9d5a,#067a45); flex:0 0 auto; }
    .stat-card .v { font-size:1.5rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .stat-card .l { font-size:.8rem; color:var(--bs-secondary-color); }
    .ent-name .lvl { color:var(--bs-secondary-color); }
    .nav-tabs .nav-link.active { font-weight:600; color:#067a45; border-bottom-color:#067a45; }
    .badge-rec { background:rgba(6,138,79,.12); color:#067a45; }
    [data-bs-theme="dark"] .badge-rec { background:rgba(6,138,79,.22); color:#7bd49f; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Clientes</h1>
        <p class="text-secondary small mb-0">Entidades, usuários e perfis sincronizados do GLPI.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('modules.map') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-geo-alt me-1"></i> Mapa</a>
        <button class="btn btn-outline-primary btn-sm" onclick="openEntity()"><i class="bi bi-diagram-3 me-1"></i> Nova entidade</button>
        <button class="btn btn-primary btn-sm" onclick="openUser()"><i class="bi bi-person-plus me-1"></i> Novo usuário</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="stat-card"><div class="ic"><i class="bi bi-building"></i></div><div><div class="v">{{ $stats['clientes'] }}</div><div class="l">Clientes / filiais</div></div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="ic"><i class="bi bi-people"></i></div><div><div class="v">{{ $stats['usuarios'] }}</div><div class="l">Usuários</div></div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="ic"><i class="bi bi-shield-lock"></i></div><div><div class="v">{{ $stats['perfis'] }}</div><div class="l">Perfis</div></div></div></div>
</div>

<div class="card">
    <div class="card-header bg-transparent">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-ent" type="button"><i class="bi bi-diagram-3 me-1"></i> Entidades</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-usr" type="button"><i class="bi bi-people me-1"></i> Usuários</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-prof" type="button"><i class="bi bi-shield-lock me-1"></i> Perfis</button></li>
        </ul>
    </div>
    <div class="card-body">
        <div class="input-group input-group-sm mb-3" style="max-width:340px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="cliFilter" class="form-control" placeholder="Filtrar nesta aba...">
        </div>

        <div class="tab-content">
            {{-- Entidades --}}
            <div class="tab-pane fade show active" id="tab-ent">
                <div class="table-wrap">
                    <table class="table table-hover align-middle mb-0 filterable">
                        <thead><tr><th>Entidade</th><th>Caminho completo</th><th class="text-end">Ações</th></tr></thead>
                        <tbody>
                            @forelse ($entities as $e)
                                <tr>
                                    <td class="ent-name">
                                        <span class="lvl">{!! str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', max(0, $e['level'] - 1)) !!}</span>
                                        @if ($e['level'] >= 3)<i class="bi bi-building text-success me-1"></i>@else<i class="bi bi-diagram-2 text-secondary me-1"></i>@endif
                                        <span class="fw-semibold">{{ $e['name'] }}</span>
                                    </td>
                                    <td class="text-secondary small">{{ $e['completename'] }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editEntity(this)"
                                                data-id="{{ $e['id'] }}" data-name="{{ $e['name'] }}"><i class="bi bi-pencil"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Nenhuma entidade.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Usuários --}}
            <div class="tab-pane fade" id="tab-usr">
                <div class="table-wrap">
                    <table class="table table-hover align-middle mb-0 filterable">
                        <thead><tr><th>Usuário</th><th>Perfil</th><th>Entidade</th><th>Escopo</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                        <tbody>
                            @forelse ($users as $u)
                                <tr>
                                    <td>
                                        <i class="bi bi-person-circle text-secondary me-1"></i>{{ $u['name'] }}
                                        @if ($u['name'] !== $u['login'])<span class="text-muted small">({{ $u['login'] }})</span>@endif
                                    </td>
                                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $u['profile'] }}</span></td>
                                    <td class="small">{{ $u['entity'] }}</td>
                                    <td>@if ($u['recursive'])<span class="badge badge-rec"><i class="bi bi-diagram-3 me-1"></i>+ subentidades</span>@else<span class="text-muted small">somente esta</span>@endif</td>
                                    <td>@if ($u['active'])<span class="badge bg-success-subtle text-success-emphasis">Ativo</span>@else<span class="badge bg-secondary-subtle text-secondary-emphasis">Inativo</span>@endif</td>
                                    <td class="text-end text-nowrap">
                                        @if (! \Illuminate\Support\Str::contains(html_entity_decode((string) $u['entity']), 'CLIENTES >'))
                                            <button class="btn btn-sm btn-outline-success" title="Isolar em entidade própria (cliente sem loja definida)" onclick="openIsolate(this)"
                                                    data-id="{{ $u['id'] }}" data-name="{{ $u['name'] }}" data-profile="{{ $u['profile_id'] }}"><i class="bi bi-shield-lock"></i></button>
                                        @endif
                                        <form method="POST" action="{{ route('directory.users.toggle', $u['id']) }}" class="d-inline">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="active" value="{{ $u['active'] ? 0 : 1 }}">
                                            <button class="btn btn-sm btn-outline-{{ $u['active'] ? 'warning' : 'success' }}" title="{{ $u['active'] ? 'Desativar' : 'Ativar' }}"><i class="bi bi-{{ $u['active'] ? 'pause-fill' : 'play-fill' }}"></i></button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editUser(this)"
                                                data-id="{{ $u['id'] }}" data-login="{{ $u['login'] }}" data-name="{{ $u['name'] }}"
                                                data-entity="{{ $u['entity_id'] }}" data-profile="{{ $u['profile_id'] }}"
                                                data-recursive="{{ $u['recursive'] ? 1 : 0 }}" data-active="{{ $u['active'] ? 1 : 0 }}"><i class="bi bi-pencil"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">Nenhum usuário.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Perfis (referência) --}}
            <div class="tab-pane fade" id="tab-prof">
                <p class="text-secondary small">Perfis são definidos no GLPI (os direitos de cada perfil são geridos lá). Aqui ficam para referência e atribuição aos usuários.</p>
                <div class="table-wrap">
                    <table class="table table-hover align-middle mb-0 filterable">
                        <thead><tr><th>Perfil</th><th>Interface</th><th class="text-end">ID</th></tr></thead>
                        <tbody>
                            @forelse ($profiles as $p)
                                <tr><td><i class="bi bi-shield-lock text-success me-1"></i>{{ $p['name'] }}</td><td class="text-secondary">{{ $p['interface'] }}</td><td class="text-end text-muted">#{{ $p['id'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Nenhum perfil.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal entidade --}}
<div class="modal fade" id="entityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="entityForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="ent_method" value="POST">
                <div class="modal-header"><h5 class="modal-title" id="ent_title">Nova entidade</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" id="ent_name" class="form-control" required>
                    </div>
                    <div class="mb-1" id="ent_parent_wrap">
                        <label class="form-label">Entidade pai</label>
                        <select name="parent_id" id="ent_parent" class="form-select">
                            @foreach ($entities as $e)
                                <option value="{{ $e['id'] }}">{{ $e['completename'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
            </form>
        </div>
    </div>
</div>

{{-- Modal usuário --}}
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="userForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="usr_method" value="POST">
                <div class="modal-header"><h5 class="modal-title" id="usr_title">Novo usuário</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Login</label>
                            <input type="text" name="login" id="usr_login" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome de exibição</label>
                            <input type="text" name="name" id="usr_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Perfil</label>
                            <select name="profile_id" id="usr_profile" class="form-select">
                                @foreach ($assignableProfiles as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Entidade</label>
                            <select name="entity_id" id="usr_entity" class="form-select">
                                @foreach ($entities as $e)<option value="{{ $e['id'] }}">{{ $e['completename'] }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha <span class="text-muted small" id="usr_pwhint"></span></label>
                        <input type="text" name="password" id="usr_password" class="form-control" autocomplete="off">
                    </div>
                    <div class="d-flex gap-4">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="recursive" value="1" id="usr_recursive"><label class="form-check-label" for="usr_recursive">Acesso recursivo (subentidades)</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="usr_active" checked><label class="form-check-label" for="usr_active">Ativo</label></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
            </form>
        </div>
    </div>
</div>

{{-- Modal isolar cliente --}}
<div class="modal fade" id="isolateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="isolateForm" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="profile_id" id="iso_profile">
            <input type="hidden" name="name" id="iso_name">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Isolar cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-secondary">Cria a entidade da loja sob <strong>CLIENTES</strong> e move <strong id="iso_who"></strong> para ela (escopo "somente esta"). Assim os chamados e ativos dele ficam isolados dos demais clientes.</p>
                <label class="form-label">Nome da entidade (loja)</label>
                <input type="text" name="entity_name" id="iso_entity" class="form-control" maxlength="255" required>
                <div class="form-text">Se já existir uma entidade com esse nome sob CLIENTES, ela é reaproveitada.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success"><i class="bi bi-shield-check me-1"></i> Isolar</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const ENT_STORE = "{{ route('directory.entities.store') }}";
    const ENT_UPD = "{{ url('diretorio/entidades') }}";
    const USR_STORE = "{{ route('directory.users.store') }}";
    const USR_UPD = "{{ url('diretorio/usuarios') }}";
    const USR_UPD_ISO = "{{ url('diretorio/usuarios') }}";
    const entModal = new bootstrap.Modal(document.getElementById('entityModal'));
    const usrModal = new bootstrap.Modal(document.getElementById('userModal'));
    const isoModal = new bootstrap.Modal(document.getElementById('isolateModal'));
    const $ = (id) => document.getElementById(id);

    window.openIsolate = function (btn) {
        $('isolateForm').action = USR_UPD_ISO + '/' + btn.dataset.id + '/isolar';
        $('iso_profile').value = btn.dataset.profile;
        $('iso_name').value = btn.dataset.name;
        $('iso_who').textContent = btn.dataset.name;
        $('iso_entity').value = btn.dataset.name;
        isoModal.show();
    };

    window.openEntity = function () {
        $('ent_title').textContent = 'Nova entidade';
        $('entityForm').action = ENT_STORE; $('ent_method').value = 'POST';
        $('ent_name').value = ''; $('ent_parent_wrap').style.display = '';
        entModal.show();
    };
    window.editEntity = function (btn) {
        $('ent_title').textContent = 'Editar entidade';
        $('entityForm').action = ENT_UPD + '/' + btn.dataset.id; $('ent_method').value = 'PUT';
        $('ent_name').value = btn.dataset.name; $('ent_parent_wrap').style.display = 'none';
        entModal.show();
    };

    window.openUser = function () {
        $('usr_title').textContent = 'Novo usuário';
        $('userForm').action = USR_STORE; $('usr_method').value = 'POST';
        $('usr_login').value = ''; $('usr_login').readOnly = false;
        $('usr_name').value = ''; $('usr_password').required = true;
        $('usr_pwhint').textContent = '(mínimo 6 caracteres)';
        $('usr_recursive').checked = false; $('usr_active').checked = true;
        usrModal.show();
    };
    window.editUser = function (btn) {
        $('usr_title').textContent = 'Editar usuário';
        $('userForm').action = USR_UPD + '/' + btn.dataset.id; $('usr_method').value = 'PUT';
        $('usr_login').value = btn.dataset.login; $('usr_login').readOnly = true;
        $('usr_name').value = btn.dataset.name;
        $('usr_profile').value = btn.dataset.profile;
        $('usr_entity').value = btn.dataset.entity;
        $('usr_recursive').checked = btn.dataset.recursive === '1';
        $('usr_active').checked = btn.dataset.active === '1';
        $('usr_password').value = ''; $('usr_password').required = false;
        $('usr_pwhint').textContent = '(deixe em branco para manter a atual)';
        usrModal.show();
    };

    // filtro por aba
    const input = $('cliFilter');
    input.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        const pane = document.querySelector('.tab-pane.active');
        if (!pane) return;
        pane.querySelectorAll('table.filterable tbody tr').forEach(function (tr) {
            if (tr.querySelector('td[colspan]')) return;
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((b) => b.addEventListener('shown.bs.tab', () => { input.value = ''; input.dispatchEvent(new Event('input')); }));
})();
</script>
@endpush
