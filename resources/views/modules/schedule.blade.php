@extends('layouts.app')
@section('title', 'Janela de atendimento — FOURLINE')
@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">Janela de atendimento</h1>
    <p class="text-muted mb-0">Horários em que o suporte funciona — base para o cálculo de SLA.</p>
</div>

<form method="POST" action="{{ route('modules.schedule.update') }}">
    @csrf
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-clock me-1"></i> Horário de funcionamento</div>
                <div class="card-body">
                    @foreach ($days as $key => $label)
                        @php $d = $window['days'][$key]; @endphp
                        <div class="row align-items-center mb-3">
                            <div class="col-5 col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="days[{{ $key }}]" id="d_{{ $key }}" @checked($d['enabled'])>
                                    <label class="form-check-label" for="d_{{ $key }}">{{ $label }}</label>
                                </div>
                            </div>
                            <div class="col-7 col-md-8 d-flex align-items-center gap-2">
                                <input type="time" name="start[{{ $key }}]" value="{{ $d['start'] }}" class="form-control form-control-sm" style="max-width:130px">
                                <span class="text-muted small">às</span>
                                <input type="time" name="end[{{ $key }}]" value="{{ $d['end'] }}" class="form-control form-control-sm" style="max-width:130px">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-calendar-x me-1"></i> Feriados (sem atendimento)</div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Um por linha (ex.: <code>25/12 — Natal</code>).</p>
                    <textarea name="holidays" rows="9" class="form-control">{{ $window['holidays'] }}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4">
        <button class="btn btn-primary btn-cta"><i class="bi bi-check2 me-1"></i> Salvar janela</button>
    </div>
</form>
@endsection
