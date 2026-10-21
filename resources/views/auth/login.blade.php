@extends('layouts.app')
@section('title', 'Entrar')
@section('auth-width', '960px')
@section('content')
<div class="login-split">
    {{-- Painel da imagem: logo, saudação e texto sobre a foto --}}
    <div class="login-hero" style="background-image:url('{{ asset('img/login-bg.jpg') }}')">
        <span class="login-deco-ring" aria-hidden="true"></span>
        <span class="login-deco-dots" aria-hidden="true"></span>
        <div class="login-hero-txt">
            <img src="{{ asset('logo-fourline.png') }}" alt="FOURLINE CONNECT" class="login-hero-logo">
            <h2>Bem-vindo de volta!</h2>
            <p>Central de chamados, inventário e monitoramento dos seus clientes, num só lugar.</p>
        </div>
    </div>

    <div class="login-form">
        <h1 class="login-title">Entrar</h1>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2"><i class="bi bi-exclamation-circle"></i>{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="login-field mb-3">
                <i class="bi bi-person"></i>
                <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus
                       autocapitalize="none" autocomplete="username" placeholder="Usuário FOURLINE" aria-label="Usuário">
            </div>
            <div class="login-field mb-3">
                <i class="bi bi-lock"></i>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Senha" aria-label="Senha">
                <button type="button" id="toggleSenha" aria-label="Mostrar senha" title="Mostrar senha"><i class="bi bi-eye"></i></button>
            </div>
            <div class="form-check mb-4">
                <input type="checkbox" name="remember" id="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Lembrar de mim neste computador</label>
            </div>
            <button class="btn btn-primary login-btn w-100">Entrar</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('toggleSenha'), campo = document.getElementById('password');
    if (!btn || !campo) return;
    btn.addEventListener('click', function () {
        var mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        btn.querySelector('i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
        btn.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
        btn.title = btn.getAttribute('aria-label');
    });
})();
</script>
@endpush
@endsection
