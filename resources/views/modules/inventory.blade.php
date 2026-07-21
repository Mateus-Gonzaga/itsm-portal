@extends('layouts.app')

@section('title', 'Inventário — FOURLINE Connect')

@push('head')
<style>
    .inv-card { border:1px solid var(--bs-border-color); border-radius:14px; padding:.9rem 1rem; display:flex; align-items:center; gap:.75rem; cursor:pointer; transition:.12s; background:transparent; width:100%; text-align:left; }
    .inv-card:hover { border-color:#A8CF45; transform:translateY(-1px); }
    .inv-card.active { border-color:#067a45; box-shadow:0 0 0 2px rgba(6,138,79,.18); }
    .inv-card .ic { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.15rem; color:#fff; background:linear-gradient(135deg,#0a9d5a,#067a45); flex:0 0 auto; }
    .inv-card .v { font-size:1.3rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .inv-card .l { font-size:.78rem; color:var(--bs-secondary-color); }
</style>
@endpush

@section('content')
<div class="mb-3">
    <h1 class="h4 mb-0">Inventário</h1>
    <p class="text-secondary small mb-0">
        Ativos do GLPI @if ($isManager) de todos os clientes @else da sua entidade @endif (inventariados pelo GLPI Agent).
    </p>
</div>

@if (session('status'))
    <div class="alert alert-success py-2 small">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
@endif

<div class="row g-2 mb-3">
    <div class="col-6 col-md">
        <button class="inv-card active" data-type="" type="button">
            <span class="ic"><i class="bi bi-box-seam"></i></span>
            <span><span class="v">{{ $total }}</span><br><span class="l">Todos</span></span>
        </button>
    </div>
    @foreach ($counts as $c)
        <div class="col-6 col-md-4 col-lg-3 col-xl-2">
            <button class="inv-card" data-type="{{ $c['label'] }}" type="button">
                <span class="ic"><i class="bi {{ $c['icon'] }}"></i></span>
                <span><span class="v">{{ $c['count'] }}</span><br><span class="l">{{ $c['label'] }}</span></span>
            </button>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <div class="input-group input-group-sm" style="max-width:320px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="invFilter" class="form-control" placeholder="Buscar por nome, série, modelo...">
            </div>
            @php $invEntities = $assets->pluck('entity')->unique()->sort()->values(); @endphp
            @if ($invEntities->count() > 1)
                <div class="input-group input-group-sm" style="max-width:340px">
                    <span class="input-group-text"><i class="bi bi-diagram-3"></i></span>
                    <select id="invEntity" class="form-select">
                        <option value="">Todas as entidades</option>
                        @foreach ($invEntities as $e)
                            <option value="{{ $e }}">{{ $e }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Tipo</th><th>Nome</th><th>Entidade</th><th>Modelo</th><th>Fabricante</th><th>Nº de série</th><th>Status</th>@if ($isManager)<th class="text-end">Ações</th>@endif</tr>
                </thead>
                <tbody id="invBody">
                    @forelse ($assets as $a)
                        <tr data-type="{{ $a['type'] }}" data-entity="{{ $a['entity'] }}">
                            <td class="text-nowrap"><i class="bi {{ $a['icon'] }} text-success me-1"></i>{{ $a['type'] }}</td>
                            <td class="fw-semibold">{{ $a['name'] }}</td>
                            <td class="small text-secondary">{{ $a['entity'] }}</td>
                            <td>{{ $a['model'] }}</td>
                            <td>{{ $a['manufacturer'] }}</td>
                            <td class="small">{{ $a['serial'] }}</td>
                            <td>@if ($a['status'] !== '—')<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $a['status'] }}</span>@else<span class="text-muted">—</span>@endif</td>
                            @if ($isManager)
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-move-asset"
                                        data-id="{{ $a['id'] }}" data-type="{{ $a['typeKey'] }}"
                                        data-name="{{ $a['name'] }}" data-entity="{{ $a['entity'] }}"
                                        title="Mover para outra entidade">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isManager ? 8 : 7 }}" class="text-center text-muted py-5">
                            <i class="bi bi-pc-display d-block fs-2 mb-2 opacity-50"></i>
                            Nenhum ativo inventariado ainda.<br><span class="small">Os equipamentos aparecem aqui conforme o GLPI Agent faz o inventário das máquinas.</span>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($isManager)
<div class="modal fade" id="moveAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('inventory.move') }}" class="modal-content">
            @csrf
            <input type="hidden" name="itemtype" id="mvItemtype">
            <input type="hidden" name="id" id="mvId">
            <div class="modal-header">
                <h5 class="modal-title">Mover ativo de entidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-2">
                    Ativo: <strong id="mvName"></strong><br>
                    Entidade atual: <span id="mvEntity" class="text-secondary"></span>
                </p>
                <label class="form-label small">Nova entidade</label>
                <select name="entity_id" class="form-select" required>
                    <option value="" selected disabled>Selecione a entidade…</option>
                    @foreach ($entities as $e)
                        <option value="{{ $e['id'] }}">{{ html_entity_decode($e['completename'] ?? $e['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Mover</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const filterInput = document.getElementById('invFilter');
    const entitySel = document.getElementById('invEntity');
    const rows = Array.from(document.querySelectorAll('#invBody tr[data-type]'));
    let typeFilter = '';
    let entityFilter = '';

    function apply() {
        const q = filterInput.value.toLowerCase();
        rows.forEach(function (tr) {
            const okType = !typeFilter || tr.dataset.type === typeFilter;
            const okEntity = !entityFilter || tr.dataset.entity === entityFilter;
            const okText = tr.textContent.toLowerCase().includes(q);
            tr.style.display = (okType && okEntity && okText) ? '' : 'none';
        });
    }
    filterInput.addEventListener('input', apply);
    if (entitySel) entitySel.addEventListener('change', function () { entityFilter = this.value; apply(); });
    document.querySelectorAll('.inv-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.inv-card').forEach((c) => c.classList.remove('active'));
            card.classList.add('active');
            typeFilter = card.dataset.type;
            apply();
        });
    });

    // Mover ativo de entidade (gestor): preenche o modal com o ativo clicado.
    const moveModalEl = document.getElementById('moveAssetModal');
    if (moveModalEl && window.bootstrap) {
        const modal = new bootstrap.Modal(moveModalEl);
        document.querySelectorAll('.js-move-asset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('mvItemtype').value = btn.dataset.type;
                document.getElementById('mvId').value = btn.dataset.id;
                document.getElementById('mvName').textContent = btn.dataset.name;
                document.getElementById('mvEntity').textContent = btn.dataset.entity;
                modal.show();
            });
        });
    }
})();
</script>
@endpush
