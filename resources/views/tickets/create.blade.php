@extends('layouts.app')
@section('title', 'Abrir chamado — FOURLINE')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h3 mb-3">Abrir chamado</h1>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('tickets.store') }}">
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
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-send me-1"></i> Enviar</button>
                        <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
