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
            @if ($isManager)
                <a href="{{ route('inventory.report') }}" id="invReport" target="_blank" class="btn btn-outline-success btn-sm ms-auto">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Exportar relatório
                </a>
            @endif
        </div>
        <div class="d-flex justify-content-end mb-2">
            <span class="badge bg-success-subtle text-success-emphasis fs-6">
                <i class="bi bi-cash-coin me-1"></i><span id="invTotalLabel">Valor total do inventário</span>:
                R$ <span id="invTotal">{{ number_format($valorTotal, 2, ',', '.') }}</span>
            </span>
        </div>
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Etiqueta</th><th>Tipo</th><th>Nome</th><th>Entidade</th><th>Modelo</th><th>Fabricante</th><th>Nº de série</th><th>Status</th><th class="text-end">Valor</th>@if ($isManager)<th class="text-end">Ações</th>@endif</tr>
                </thead>
                <tbody id="invBody">
                    @forelse ($assets as $a)
                        <tr data-type="{{ $a['type'] }}" data-entity="{{ $a['entity'] }}" data-value="{{ $a['value'] ?? 0 }}"
                            data-id="{{ $a['id'] }}" data-typekey="{{ $a['typeKey'] }}"
                            @if ($a['typeKey'] !== 'Peripheral') class="js-asset-row" style="cursor:pointer" title="Ver detalhes técnicos" @endif>
                            <td class="text-nowrap">@if (! empty($a['tag']))<span class="badge bg-dark-subtle text-dark-emphasis font-monospace">{{ $a['tag'] }}</span>@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-nowrap"><i class="bi {{ $a['icon'] }} text-success me-1"></i>{{ $a['type'] }}</td>
                            <td class="fw-semibold">{{ $a['name'] }}</td>
                            <td class="small text-secondary">{{ $a['entity'] }}</td>
                            <td>{{ $a['model'] }}</td>
                            <td>{{ $a['manufacturer'] }}</td>
                            <td class="small">{{ $a['serial'] }}</td>
                            <td>@if ($a['status'] !== '—')<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $a['status'] }}</span>@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-end text-nowrap">
                                @if (! empty($a['value']))<span class="text-success fw-semibold">R$ {{ number_format($a['value'], 2, ',', '.') }}</span>@else<span class="text-muted">—</span>@endif
                                @if ($isManager)
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-1 text-decoration-none js-set-value" title="Editar etiqueta/valor"
                                            data-id="{{ $a['id'] }}" data-type="{{ $a['typeKey'] }}" data-name="{{ $a['name'] }}" data-value="{{ $a['value'] }}" data-tag="{{ $a['tag'] }}" data-modelo="{{ $a['modelo'] }}"><i class="bi bi-pencil"></i></button>
                                @endif
                            </td>
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
                        <tr><td colspan="{{ $isManager ? 10 : 9 }}" class="text-center text-muted py-5">
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

<div class="modal fade" id="valueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('inventory.value') }}" class="modal-content">
            @csrf
            <input type="hidden" name="itemtype" id="vlItemtype">
            <input type="hidden" name="id" id="vlId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tag me-2 text-success"></i>Editar ativo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-2">Ativo: <strong id="vlName"></strong></p>
                <label class="form-label small">Etiqueta / patrimônio</label>
                <input type="text" name="tag" id="vlTag" class="form-control mb-3" maxlength="60" placeholder="Ex.: FL-0042">
                <label class="form-label small">Modelo</label>
                <input type="text" name="modelo" id="vlModelo" class="form-control mb-3" maxlength="120" placeholder="Ex.: Brother DCP-1610NW">
                <label class="form-label small">Valor (R$)</label>
                <input type="number" step="0.01" min="0" name="value" id="vlValue" class="form-control" placeholder="0,00">
                <div class="form-text">O modelo do GLPI aparece automático quando existe; aqui você informa/ajusta manualmente. Deixe tudo em branco e salve para <strong>limpar</strong>.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Salvar</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Detalhes técnicos do computador (CPU/RAM/disco/SO + datas) --}}
<div class="modal fade" id="pcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pc-display me-2 text-success"></i><span id="pcName">Ativo</span>
                    <span class="badge bg-success-subtle text-success-emphasis ms-1" id="pcType"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pcLoading" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2"></div> Carregando detalhes…
                </div>
                <div id="pcError" class="alert alert-warning py-2 small d-none"></div>
                <dl id="pcBody" class="row mb-0 d-none"></dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const filterInput = document.getElementById('invFilter');
    const entitySel = document.getElementById('invEntity');
    const rows = Array.from(document.querySelectorAll('#invBody tr[data-type]'));
    let typeFilter = '';
    let entityFilter = '';

    // Estado inicial dos filtros vindo da URL — preserva a tela/pesquisa ao
    // voltar de um salvamento (o back() do servidor mantém a query string).
    const params = new URLSearchParams(location.search);
    if (filterInput && params.get('q')) filterInput.value = params.get('q');
    entityFilter = params.get('entidade') || '';
    typeFilter = params.get('tipo') || '';
    if (entitySel && entityFilter) entitySel.value = entityFilter;
    document.querySelectorAll('.inv-card').forEach(function (c) {
        c.classList.toggle('active', (c.dataset.type || '') === typeFilter);
    });

    // Mantém a URL em sincronia com os filtros visíveis (via replaceState).
    function syncUrl() {
        const p = new URLSearchParams();
        if (filterInput && filterInput.value) p.set('q', filterInput.value);
        if (entityFilter) p.set('entidade', entityFilter);
        if (typeFilter) p.set('tipo', typeFilter);
        const qs = p.toString();
        history.replaceState(null, '', qs ? location.pathname + '?' + qs : location.pathname);
    }

    const totalEl = document.getElementById('invTotal');
    const totalLabel = document.getElementById('invTotalLabel');
    function fmtBRL(n) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function apply() {
        const q = filterInput.value.toLowerCase();
        let soma = 0;
        rows.forEach(function (tr) {
            const okType = !typeFilter || tr.dataset.type === typeFilter;
            const okEntity = !entityFilter || tr.dataset.entity === entityFilter;
            const okText = tr.textContent.toLowerCase().includes(q);
            const visivel = okType && okEntity && okText;
            tr.style.display = visivel ? '' : 'none';
            if (visivel) soma += parseFloat(tr.dataset.value || '0') || 0;
        });
        if (totalEl) totalEl.textContent = fmtBRL(soma);
        if (totalLabel) totalLabel.textContent = entityFilter ? 'Valor dos ativos desta entidade' : 'Valor total do inventário';
        syncUrl();
    }
    filterInput.addEventListener('input', apply);
    // Mantém o link do relatório apontando para a entidade filtrada (ou todas).
    const reportLink = document.getElementById('invReport');
    const reportBase = reportLink ? reportLink.getAttribute('href') : '';
    function syncReport() {
        if (!reportLink) return;
        reportLink.href = entityFilter ? reportBase + '?entidade=' + encodeURIComponent(entityFilter) : reportBase;
    }
    if (entitySel) entitySel.addEventListener('change', function () { entityFilter = this.value; apply(); syncReport(); });
    document.querySelectorAll('.inv-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.inv-card').forEach((c) => c.classList.remove('active'));
            card.classList.add('active');
            typeFilter = card.dataset.type;
            apply();
        });
    });

    // Aplica os filtros vindos da URL já na abertura (preserva ao voltar de salvar).
    apply();
    syncReport();

    // Definir valor do ativo (gestor).
    const valueModalEl = document.getElementById('valueModal');
    if (valueModalEl && window.bootstrap) {
        const vmodal = new bootstrap.Modal(valueModalEl);
        document.querySelectorAll('.js-set-value').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('vlItemtype').value = btn.dataset.type;
                document.getElementById('vlId').value = btn.dataset.id;
                document.getElementById('vlName').textContent = btn.dataset.name;
                document.getElementById('vlTag').value = btn.dataset.tag || '';
                document.getElementById('vlModelo').value = btn.dataset.modelo || '';
                document.getElementById('vlValue').value = btn.dataset.value || '';
                vmodal.show();
            });
        });
    }

    // Detalhes técnicos do ativo: clique na linha abre o modal e busca via AJAX.
    const pcModalEl = document.getElementById('pcModal');
    if (pcModalEl && window.bootstrap) {
        const pcModal = new bootstrap.Modal(pcModalEl);
        const ASSET_URL = "{{ url('inventario/ativo') }}";
        const body = document.getElementById('pcBody');

        function row(label, value) {
            const dt = document.createElement('dt');
            dt.className = 'col-sm-4 text-secondary fw-normal';
            dt.textContent = label;
            const dd = document.createElement('dd');
            dd.className = 'col-sm-8 fw-semibold';
            dd.textContent = value || '—';
            body.append(dt, dd);
        }

        document.querySelectorAll('.js-asset-row').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('button, a, form')) return; // não abre ao clicar em ações
                const id = tr.dataset.id;
                const itemtype = tr.dataset.typekey;
                document.getElementById('pcName').textContent = tr.querySelector('td:nth-child(3)')?.textContent.trim() || 'Ativo';
                document.getElementById('pcType').textContent = '';
                body.innerHTML = '';
                body.classList.add('d-none');
                document.getElementById('pcError').classList.add('d-none');
                document.getElementById('pcLoading').classList.remove('d-none');
                pcModal.show();

                fetch(ASSET_URL + '/' + itemtype + '/' + id, { headers: { 'Accept': 'application/json' } })
                    .then((r) => { if (!r.ok) throw new Error('Não foi possível carregar os detalhes.'); return r.json(); })
                    .then(function (d) {
                        document.getElementById('pcName').textContent = d.name || 'Ativo';
                        document.getElementById('pcType').textContent = d.type || '';
                        body.innerHTML = '';
                        (d.fields || []).forEach((f) => row(f.label, f.value));
                        row('Cadastrado no GLPI', d.createdAt);
                        row('Último inventário', d.updatedAt);
                        document.getElementById('pcLoading').classList.add('d-none');
                        body.classList.remove('d-none');
                    })
                    .catch(function (err) {
                        document.getElementById('pcLoading').classList.add('d-none');
                        const box = document.getElementById('pcError');
                        box.textContent = err.message || 'Erro ao carregar.';
                        box.classList.remove('d-none');
                    });
            });
        });
    }

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
