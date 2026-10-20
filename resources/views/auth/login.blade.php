@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="card auth-card border-0">
    <div class="card-body p-4 p-sm-5">
        <div class="text-center mb-4">
            <img src="{{ asset('logo-fourline.png') }}" alt="FOURLINE CONNECT" style="max-width: 230px; height: auto">
            <p class="auth-kicker mb-0 mt-2">Central de Chamados</p>
        </div>

        <h1 class="auth-title">Bem-vindo de volta</h1>
        <p class="auth-sub">Entre com seu usuário e senha FOURLINE.</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2"><i class="bi bi-exclamation-circle"></i>{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="login">Usuário</label>
                <div class="input-group auth-input">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" id="login" name="login" value="{{ old('login') }}" class="form-control" required autofocus
                           autocapitalize="none" autocomplete="username" placeholder="seu usuário FOURLINE">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Senha</label>
                <div class="input-group auth-input">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                    <button type="button" class="btn btn-outline-secondary" id="toggleSenha" aria-label="Mostrar senha" title="Mostrar senha">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="form-check mb-4">
                <input type="checkbox" name="remember" id="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Lembrar de mim neste computador</label>
            </div>
            <button class="btn btn-primary btn-cta w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
            </button>
        </form>
    </div>
</div>
<p class="auth-help">Esqueceu a senha ou não tem acesso? Fale com a equipe FOURLINE.</p>

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
