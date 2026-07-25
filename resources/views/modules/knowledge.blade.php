@extends('layouts.app')

@section('title', 'Base de Conhecimento — FOURLINE Connect')

@push('head')
<style>
    .kb-stat { border:1px solid var(--bs-border-color); border-radius:14px; padding:1rem 1.25rem; display:flex; align-items:center; gap:.9rem; }
    .kb-stat .ic { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; color:#fff; background:linear-gradient(135deg,#0a9d5a,#067a45); flex:0 0 auto; }
    .kb-stat .v { font-size:1.4rem; font-weight:700; font-family:'Rajdhani',sans-serif; line-height:1; }
    .kb-stat .l { font-size:.8rem; color:var(--bs-secondary-color); }
    .kb-card { border:1px solid var(--bs-border-color); border-radius:12px; padding:1rem 1.1rem; height:100%; transition:.12s; }
    .kb-card:hover { border-color:#A8CF45; box-shadow:0 4px 14px rgba(3,61,34,.08); }
    .kb-card .kb-conteudo { white-space:pre-line; font-size:.9rem; color:var(--bs-body-color); max-height:8rem; overflow:hidden; position:relative; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Base de conhecimento</h1>
        <p class="text-secondary small mb-0">Informações por cliente/filial: contratos, estimativa de valor dos ativos, infraestrutura e observações.</p>
    </div>
    <button class="btn btn-success btn-sm" onclick="openKb()"><i class="bi bi-plus-lg me-1"></i> Novo registro</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6"><div class="kb-stat"><div class="ic"><i class="bi bi-journal-text"></i></div><div><div class="v">{{ $total }}</div><div class="l">Registros na base</div></div></div></div>
    <div class="col-sm-6"><div class="kb-stat"><div class="ic"><i class="bi bi-cash-coin"></i></div><div><div class="v">R$ {{ number_format($valorTotal, 2, ',', '.') }}</div><div class="l">Valor estimado total dos ativos</div></div></div></div>
</div>

<form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <div class="input-group input-group-sm" style="max-width:320px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por cliente, título, conteúdo...">
    </div>
    <select name="categoria" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()">
        <option value="">Todas as categorias</option>
        @foreach ($categorias as $c)
            <option value="{{ $c }}" @selected($catSel === $c)>{{ $c }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filtrar</button>
    @if ($q || $catSel)<a href="{{ route('modules.kb') }}" class="btn btn-link btn-sm text-decoration-none">limpar</a>@endif
</form>

<div class="row g-3">
    @forelse ($artigos as $a)
        <div class="col-md-6 col-xl-4">
            <div class="kb-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <span class="badge bg-success-subtle text-success-emphasis">{{ $a->categoria }}</span>
                    <div class="text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Editar" onclick="openKb(this)"
                                data-id="{{ $a->id }}" data-cliente="{{ $a->cliente }}" data-categoria="{{ $a->categoria }}"
                                data-titulo="{{ $a->titulo }}" data-valor="{{ $a->valor }}" data-conteudo="{{ $a->conteudo }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('kb.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Excluir este registro?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                @if ($a->cliente)<div class="small text-secondary"><i class="bi bi-building me-1"></i>{{ $a->cliente }}</div>@endif
                <div class="fw-semibold mt-1">{{ $a->titulo }}</div>
                @if ($a->valor)<div class="small text-success fw-semibold mt-1"><i class="bi bi-cash me-1"></i>R$ {{ number_format($a->valor, 2, ',', '.') }}</div>@endif
                @if ($a->conteudo)<div class="kb-conteudo mt-2">{{ $a->conteudo }}</div>@endif
                @if ($a->attachments->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach ($a->attachments as $anexo)
                            @php $url = route('kb.attachment', $anexo); @endphp
                            <div class="position-relative">
                                @if ($anexo->isImage())
                                    <a href="{{ $url }}" target="_blank" title="{{ $anexo->original_name }}" class="d-block border rounded overflow-hidden" style="width:64px;height:64px">
                                        <img src="{{ $url }}" style="width:100%;height:100%;object-fit:cover" loading="lazy" alt="{{ $anexo->original_name }}">
                                    </a>
                                @else
                                    <a href="{{ $url }}" target="_blank" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" title="{{ $anexo->original_name }}">
                                        <i class="bi bi-file-earmark-text"></i><span class="small text-truncate" style="max-width:120px">{{ $anexo->original_name }}</span>
                                    </a>
                                @endif
                                <form method="POST" action="{{ route('kb.attachment.destroy', $anexo) }}" class="position-absolute top-0 end-0" onsubmit="return confirm('Remover este anexo?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger p-0 d-flex align-items-center justify-content-center" style="width:18px;height:18px;border-radius:50%;font-size:.7rem" title="Remover"><i class="bi bi-x"></i></button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="text-muted mt-2" style="font-size:.72rem">Atualizado em {{ $a->updated_at?->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="text-center text-muted py-5">
                <i class="bi bi-journal-text d-block fs-1 mb-2 opacity-50"></i>
                Nenhum registro ainda.<br><span class="small">Clique em <strong>Novo registro</strong> para cadastrar o primeiro (ex.: contrato de um cliente).</span>
            </div>
        </div>
    @endforelse
</div>

<datalist id="kbClientes">
    @foreach ($clientes as $c)<option value="{{ $c }}">@endforeach
</datalist>

{{-- Modal: novo/editar registro --}}
<div class="modal fade" id="kbModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="kbForm" method="POST" class="modal-content" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="kb_method" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="kb_title_h">Novo registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label">Cliente / filial</label>
                        <input type="text" name="cliente" id="kb_cliente" class="form-control" list="kbClientes" placeholder="Ex.: Drogacei — FL 01">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="categoria" id="kb_categoria" class="form-select">
                            @foreach ($categorias as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" id="kb_titulo" class="form-control" maxlength="255" required placeholder="Ex.: Contrato de manutenção mensal">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Valor estimado (R$) <span class="text-muted small">— opcional</span></label>
                        <input type="number" step="0.01" min="0" name="valor" id="kb_valor" class="form-control" placeholder="0,00">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Conteúdo / observações</label>
                    <textarea name="conteudo" id="kb_conteudo" rows="6" class="form-control" placeholder="Detalhes do contrato, lista de ativos, valores, contatos, particularidades da filial..."></textarea>
                </div>
                <div class="mb-1">
                    <label class="form-label">Anexos <span class="text-muted small">— contrato em PDF, imagem, planilha... (até 15 MB cada)</span></label>
                    <input type="file" name="files[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,image/*">
                    <div class="form-text" id="kb_anexo_hint">Na edição, os arquivos aqui são <strong>adicionados</strong> aos já existentes (que ficam no card).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Salvar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const STORE = "{{ route('kb.store') }}";
    const BASE = "{{ url('base-conhecimento') }}"; // + '/' + id (PUT)
    const modal = new bootstrap.Modal(document.getElementById('kbModal'));
    const $ = (id) => document.getElementById(id);

    window.openKb = function (el) {
        const form = $('kbForm');
        if (el) {
            $('kb_title_h').textContent = 'Editar registro';
            form.action = BASE + '/' + el.dataset.id;
            $('kb_method').value = 'PUT';
            $('kb_cliente').value = el.dataset.cliente || '';
            $('kb_categoria').value = el.dataset.categoria || 'Geral';
            $('kb_titulo').value = el.dataset.titulo || '';
            $('kb_valor').value = el.dataset.valor || '';
            $('kb_conteudo').value = el.dataset.conteudo || '';
        } else {
            $('kb_title_h').textContent = 'Novo registro';
            form.action = STORE;
            $('kb_method').value = 'POST';
            form.reset();
        }
        modal.show();
    };
})();
</script>
@endpush
