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
                <button type="button" class="btn btn-success btn-sm ms-auto js-new-asset">
                    <i class="bi bi-plus-lg me-1"></i> Novo ativo
                </button>
                <a href="{{ route('inventory.report') }}" id="invReport" target="_blank" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Exportar relatório
                </a>
            @endif
        </div>
        @if ($canSeeValues)
            <div class="d-flex justify-content-end mb-2">
                <span class="badge bg-success-subtle text-success-emphasis fs-6">
                    <i class="bi bi-cash-coin me-1"></i><span id="invTotalLabel">Valor total do inventário</span>:
                    R$ <span id="invTotal">{{ number_format($valorTotal, 2, ',', '.') }}</span>
                </span>
            </div>
        @endif
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Etiqueta</th><th>Tipo</th><th>Nome</th><th>Entidade</th><th>Modelo</th><th>Marca</th><th>Nº de série</th><th>Status</th><th class="text-nowrap">Adicionado em</th>@if ($canSeeValues)<th class="text-end">Valor</th>@endif @if ($isManager)<th class="text-end">Ações</th>@endif</tr>
                </thead>
                <tbody id="invBody">
                    @forelse ($assets as $a)
                        <tr data-type="{{ $a['type'] }}" data-entity="{{ $a['entity'] }}" @if ($canSeeValues) data-value="{{ $a['value'] ?? 0 }}" @endif
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
                            <td class="small text-secondary text-nowrap">{{ ($a['created'] ?? '') ?: '—' }}</td>
                            @if ($canSeeValues)
                                <td class="text-end text-nowrap">
                                    @if (! empty($a['value']))<span class="text-success fw-semibold">R$ {{ number_format($a['value'], 2, ',', '.') }}</span>@else<span class="text-muted">—</span>@endif
                                </td>
                            @endif
                            @if ($isManager)
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary js-edit-asset"
                                        data-id="{{ $a['id'] }}" data-type="{{ $a['typeKey'] }}" data-name="{{ $a['name'] }}"
                                        data-entity-id="{{ $a['entityId'] ?? 0 }}" data-entity-name="{{ $a['entity'] }}"
                                        data-marca="{{ $a['manufacturer'] === '—' ? '' : $a['manufacturer'] }}"
                                        data-modelo="{{ $a['model'] === '—' ? '' : $a['model'] }}"
                                        data-serial="{{ ($a['rawSerial'] ?? $a['serial']) === '—' ? '' : ($a['rawSerial'] ?? $a['serial']) }}"
                                        data-tag="{{ $a['tag'] ?? '' }}" data-value="{{ $a['value'] ?? '' }}"
                                        data-comment="{{ $a['comment'] ?? '' }}"
                                        title="Editar cadastro do ativo">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-move-asset"
                                        data-id="{{ $a['id'] }}" data-type="{{ $a['typeKey'] }}"
                                        data-name="{{ $a['name'] }}" data-entity="{{ $a['entity'] }}"
                                        title="Mover para outra entidade">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </button>
                                    <form method="POST" action="{{ route('inventory.destroy') }}" class="d-inline"
                                          onsubmit="return confirm('Excluir o ativo &quot;{{ $a['name'] }}&quot;? Ele vai para a lixeira do GLPI.')">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="itemtype" value="{{ $a['typeKey'] }}">
                                        <input type="hidden" name="id" value="{{ $a['id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir ativo"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ 9 + ($canSeeValues ? 1 : 0) + ($isManager ? 1 : 0) }}" class="text-center text-muted py-5">
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

<div class="modal fade" id="editAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route('inventory.update') }}" class="modal-content">
            @csrf @method('PUT')
            <input type="hidden" name="itemtype" id="edItemtype">
            <input type="hidden" name="id" id="edId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Ativo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nome do equipamento <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edName" class="form-control" maxlength="255" required placeholder="Ex.: PC-CAIXA-01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Entidade (filial)</label>
                        <select name="entity_id" id="edEntityId" class="form-select">
                            <option value="">(Manter entidade atual)</option>
                            @foreach ($entities as $e)
                                <option value="{{ $e['id'] }}">{{ html_entity_decode($e['completename'] ?? $e['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Marca</label>
                        <input type="text" name="marca" id="edMarca" class="form-control" maxlength="120" placeholder="Ex.: Dell, SMS, Intelbras, HP, Samsung...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Modelo</label>
                        <input type="text" name="modelo" id="edModelo" class="form-control" maxlength="120" placeholder="Ex.: OptiPlex 3080, Net Station 1500VA...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nº de série</label>
                        <input type="text" name="serial" id="edSerial" class="form-control" maxlength="120" placeholder="Ex.: SN-AB1234">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Etiqueta / Patrimônio</label>
                        <input type="text" name="tag" id="edTag" class="form-control" maxlength="60" placeholder="Ex.: FL-0042">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Valor (R$)</label>
                        <input type="number" step="0.01" min="0" name="value" id="edValue" class="form-control" placeholder="0,00">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Observações</label>
                        <textarea name="comment" id="edComment" rows="2" class="form-control" maxlength="2000" placeholder="Anotações gerais do ativo..."></textarea>
                    </div>
                </div>
                <div class="form-text mt-2 small text-muted">
                    As alterações são sincronizadas com o GLPI (nome, série, entidade, marca) e guardadas no portal (etiqueta, modelo e valor financeiro).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="newAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route('inventory.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-success"></i>Novo ativo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small">Tipo</label>
                        <select name="itemtype" class="form-select" required>
                            @foreach ($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small">Entidade (filial)</label>
                        <select name="entity_id" class="form-select" required>
                            <option value="" selected disabled>Selecione a entidade…</option>
                            @foreach ($entities as $e)
                                <option value="{{ $e['id'] }}">{{ html_entity_decode($e['completename'] ?? $e['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small">Nome do equipamento</label>
                        <input type="text" name="name" class="form-control" maxlength="255" required placeholder="Ex.: PDV-03-DF-02">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Marca <span class="text-muted">— opcional</span></label>
                        <input type="text" name="marca" class="form-control" maxlength="120" placeholder="Ex.: Dell, SMS, Intelbras, HP...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Modelo <span class="text-muted">— opcional</span></label>
                        <input type="text" name="modelo" class="form-control" maxlength="120" placeholder="Ex.: Dell OptiPlex 3080">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Nº de série <span class="text-muted">— opcional</span></label>
                        <input type="text" name="serial" class="form-control" maxlength="120">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Etiqueta <span class="text-muted">— opcional</span></label>
                        <input type="text" name="tag" class="form-control" maxlength="60" placeholder="FL-0042">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Valor (R$) <span class="text-muted">— opcional</span></label>
                        <input type="number" step="0.01" min="0" name="value" class="form-control" placeholder="0,00">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Observações <span class="text-muted">— opcional</span></label>
                        <textarea name="comment" rows="2" class="form-control" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="form-text mt-2">O ativo é criado no GLPI na entidade escolhida. Marca, etiqueta, modelo e valor ficam guardados no portal (o valor e a marca também são refletidos no GLPI).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Criar ativo</button>
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
        // Contagem dos cards acompanha entidade + busca (sem o filtro de tipo).
        const porTipo = {};
        let totalFiltro = 0;
        rows.forEach(function (tr) {
            const okType = !typeFilter || tr.dataset.type === typeFilter;
            const okEntity = !entityFilter || tr.dataset.entity === entityFilter;
            const okText = tr.textContent.toLowerCase().includes(q);
            if (okEntity && okText) {
                porTipo[tr.dataset.type] = (porTipo[tr.dataset.type] || 0) + 1;
                totalFiltro++;
            }
            const visivel = okType && okEntity && okText;
            tr.style.display = visivel ? '' : 'none';
            if (visivel) soma += parseFloat(tr.dataset.value || '0') || 0;
        });
        document.querySelectorAll('.inv-card').forEach(function (c) {
            const v = c.querySelector('.v');
            if (v) v.textContent = c.dataset.type ? (porTipo[c.dataset.type] || 0) : totalFiltro;
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

    // Editar cadastro completo do ativo (gestor).
    const editModalEl = document.getElementById('editAssetModal');
    if (editModalEl && window.bootstrap) {
        const editModal = new bootstrap.Modal(editModalEl);
        document.querySelectorAll('.js-edit-asset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edItemtype').value = btn.dataset.type || '';
                document.getElementById('edId').value = btn.dataset.id || '';
                document.getElementById('edName').value = btn.dataset.name || '';
                if (document.getElementById('edEntityId')) {
                    document.getElementById('edEntityId').value = btn.dataset.entityId || '';
                }
                document.getElementById('edMarca').value = btn.dataset.marca || '';
                document.getElementById('edModelo').value = btn.dataset.modelo || '';
                document.getElementById('edSerial').value = btn.dataset.serial || '';
                document.getElementById('edTag').value = btn.dataset.tag || '';
                document.getElementById('edValue').value = btn.dataset.value || '';
                document.getElementById('edComment').value = btn.dataset.comment || '';
                editModal.show();
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

    // Novo ativo (gestor).
    const newAssetEl = document.getElementById('newAssetModal');
    if (newAssetEl && window.bootstrap) {
        const naModal = new bootstrap.Modal(newAssetEl);
        document.querySelectorAll('.js-new-asset').forEach(function (btn) {
            btn.addEventListener('click', function () { naModal.show(); });
        });
    }
})();
</script>
@endpush
