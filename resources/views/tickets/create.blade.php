@extends('layouts.app')
@section('title', 'Abrir chamado — FOURLINE')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h3 mb-3">Abrir chamado</h1>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" value="{{ old('title') }}"
                               class="form-control @error('title') is-invalid @enderror" required>
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror">
                                @foreach ($types as $tp)
                                    <option value="{{ $tp->value }}" @selected(old('type') === $tp->value)>{{ $tp->label() }}</option>
                                @endforeach
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Categoria</label>
                            <select name="category" class="form-select">
                                <option value="">— selecione —</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Prioridade</label>
                            <select name="priority" class="form-select @error('priority') is-invalid @enderror">
                                @foreach ($priorities as $p)
                                    <option value="{{ $p->value }}" @selected(old('priority') === $p->value)>{{ $p->label() }}</option>
                                @endforeach
                            </select>
                            @error('priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    @if ($isStaff)
                        <div class="row align-items-end p-2 mb-3 rounded" style="background:var(--bs-tertiary-bg)">
                            <div class="col-12 mb-2">
                                <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-headset me-1"></i>Abertura pela equipe</span>
                                <span class="text-muted small ms-1">— ex.: demanda recebida no WhatsApp</span>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Solicitante (cliente)</label>
                                <select id="requesterSelect" class="form-select">
                                    <option value="">— eu mesmo ({{ auth()->user()->name }}) —</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c['id'] }}" data-name="{{ $c['name'] }}"
                                            @selected((string) old('requester_glpi_id') === (string) $c['id'])>
                                            {{ $c['name'] }}@if ($c['entity']) — {{ $c['entity'] }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="requester_glpi_id" id="requester_glpi_id" value="{{ old('requester_glpi_id') }}">
                                <input type="hidden" name="requester_name" id="requester_name" value="{{ old('requester_name') }}">
                                <div class="form-text">Em nome de quem o chamado é aberto. Vazio = você.</div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Técnico responsável <span class="text-muted small">— opcional</span></label>
                                <select id="technicianSelect" class="form-select">
                                    <option value="">— definir depois —</option>
                                    @foreach ($technicians as $t)
                                        <option value="{{ $t['id'] }}" data-name="{{ $t['name'] }}"
                                            @selected((string) old('technician_glpi_id') === (string) $t['id'])>
                                            {{ $t['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="technician_glpi_id" id="technician_glpi_id" value="{{ old('technician_glpi_id') }}">
                                <input type="hidden" name="technician_name" id="technician_name" value="{{ old('technician_name') }}">
                                <div class="form-text">Já direciona o chamado ao técnico.</div>
                            </div>
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prazo (SLA) <span class="text-muted small">— opcional</span></label>
                            <input type="datetime-local" name="due_date" value="{{ old('due_date') }}"
                                   class="form-control @error('due_date') is-invalid @enderror">
                            <div class="form-text">Data-limite de atendimento. Aparece na Agenda.</div>
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="description" rows="5"
                                  class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Anexos <span class="text-muted small">— opcional (foto do problema, PDF)</span></label>
                        <input type="file" name="files[]" class="form-control @error('files.*') is-invalid @enderror"
                               accept="image/*,.pdf" multiple>
                        <div class="form-text">Imagens ou PDF, até 8 MB cada (máx. 5 arquivos).</div>
                        @error('files.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-send me-1"></i> Enviar</button>
                        <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@if ($isStaff)
<script>
    // Copia id + nome do <select> para os campos ocultos enviados no formulário.
    (function () {
        function bind(selId, idField, nameField) {
            var sel = document.getElementById(selId);
            if (!sel) return;
            function sync() {
                var opt = sel.options[sel.selectedIndex];
                document.getElementById(idField).value = sel.value || '';
                document.getElementById(nameField).value = sel.value ? (opt.dataset.name || '') : '';
            }
            sel.addEventListener('change', sync);
            sync();
        }
        bind('requesterSelect', 'requester_glpi_id', 'requester_name');
        bind('technicianSelect', 'technician_glpi_id', 'technician_name');
    })();
</script>
@endif
@endsection
