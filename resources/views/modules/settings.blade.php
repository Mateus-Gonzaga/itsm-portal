@extends('layouts.app')
@section('title', 'Configurações — FOURLINE')
@section('content')
@php $u = auth()->user(); $glpiDriver = config('glpi.driver'); @endphp

<div class="mb-4">
    <h1 class="h3 mb-0">Configurações</h1>
    <p class="text-muted mb-0">Preferências e parâmetros do portal.</p>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        {{-- Aparência --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-palette me-1"></i> Aparência</div>
            <div class="card-body">
                <p class="text-muted small mb-2">Tema da interface (salvo neste navegador).</p>
                <div class="btn-group" role="group" aria-label="Tema">
                    <button type="button" class="btn btn-outline-secondary" onclick="setTheme('light')"><i class="bi bi-sun me-1"></i> Claro</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setTheme('dark')"><i class="bi bi-moon-stars me-1"></i> Escuro</button>
                </div>
            </div>
        </div>

        {{-- Conta --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-person-circle me-1"></i> Conta</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4">Nome</dt><dd class="col-8">{{ $u->name }}</dd>
                    <dt class="col-4">E-mail</dt><dd class="col-8">{{ $u->email }}</dd>
                    <dt class="col-4">Perfil</dt><dd class="col-8">{{ $u->role->label() }}</dd>
                </dl>
                <p class="text-muted small mt-3 mb-0">
                    Editar dados e trocar senha:
                    <span class="badge rounded-pill" style="background:var(--fl-green-light);color:var(--fl-green-xdark)">em breve</span>
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        {{-- Integração GLPI --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-plug me-1"></i> Integração GLPI</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4">Modo</dt>
                    <dd class="col-8">
                        @if ($glpiDriver === 'api')
                            <span class="badge bg-success">API (GLPI real)</span>
                        @else
                            <span class="badge bg-secondary">Fake (dados de teste)</span>
                        @endif
                    </dd>
                    <dt class="col-4">URL da API</dt><dd class="col-8">{{ config('glpi.api.url') ?: '—' }}</dd>
                </dl>
                <p class="text-muted small mt-3 mb-0">A troca Fake/API é feita por <code>GLPI_DRIVER</code> no <code>.env</code> (Fase 2).</p>
            </div>
        </div>

        {{-- Notificações --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-bell me-1"></i> Notificações</div>
            <div class="card-body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="n1" checked disabled>
                    <label class="form-check-label" for="n1">Avisar por e-mail sobre atualizações dos chamados</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="n2" checked disabled>
                    <label class="form-check-label" for="n2">Notificações no portal</label>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    Preferências de notificação:
                    <span class="badge rounded-pill" style="background:var(--fl-green-light);color:var(--fl-green-xdark)">em breve</span>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    function setTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', t);
        localStorage.setItem('theme', t);
    }
</script>
@endsection
