@extends('layouts.app')
@section('title', 'Entrar — FOURLINE')
@section('content')
<div class="card auth-card border-0" style="border-top: 4px solid var(--fl-green-light) !important">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <img src="{{ asset('logo-fourline.png') }}" alt="FOURLINE CONNECT" style="max-width: 240px; height: auto">
            <p class="text-muted small mb-0 mt-2" style="letter-spacing: 3px; text-transform: uppercase">Central de Chamados</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Usuário</label>
                <input type="text" name="login" value="{{ old('login') }}" class="form-control" required autofocus
                       autocapitalize="none" autocomplete="username" placeholder="seu usuário do GLPI">
            </div>
            <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="remember" id="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Lembrar de mim</label>
            </div>
            <button class="btn btn-primary btn-cta w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
            </button>
        </form>
    </div>
</div>
<p class="text-center text-muted small mt-3 mb-0">
    Entre com seu <strong>usuário do GLPI</strong>.
</p>
@endsection
