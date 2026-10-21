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
            {{-- Cópia do logo só com "FOURLINE" colorido (o "CONNECT" preto some no fundo escuro) --}}
            <img src="{{ asset('logo-fourline-cortada.png') }}" alt="FOURLINE" class="login-hero-logo">
            <h2>Bem-vindo de volta!</h2>
            <p>Central de chamados, inventário e monitoramento dos seus clientes, num só lugar.</p>
        </div>
    </div>

    <div class="login-form">
        <h1 class="login-title">Entrar</h1>
        <p class="login-sub">Acesse com o usuário e a senha FOURLINE.</p>

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
            <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
                <div class="form-check mb-0">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input">
                    <label for="remember" class="form-check-label">Lembrar de mim</label>
                </div>
                {{-- A senha é a do GLPI: a troca é feita pela equipe FOURLINE --}}
                <a href="mailto:contato@fourline.com.br?subject=Acesso%20ao%20portal%20FOURLINE" class="login-link">Esqueci minha senha</a>
            </div>
            <button class="btn btn-primary login-btn w-100">Entrar</button>
        </form>
        <p class="login-help">Problemas para acessar? Fale com o administrador do sistema.</p>
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
