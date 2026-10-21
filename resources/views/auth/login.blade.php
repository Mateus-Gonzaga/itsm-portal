@extends('layouts.app')
@section('title', 'Entrar')
@section('auth-width', '980px')
@section('content')
<div class="login-split">
    {{-- Painel da marca: onda de pontos verdes desenhada em canvas (sem imagem externa) --}}
    <div class="login-hero" aria-hidden="true">
        <canvas id="loginWave"></canvas>
        <div class="login-hero-txt">
            <span class="login-hero-kicker">FOURLINE Connect</span>
            <h2>Bem-vindo de volta!</h2>
            <p>Central de chamados, inventário e monitoramento dos seus clientes, num só lugar.</p>
        </div>
    </div>

    <div class="login-form">
        <img src="{{ asset('logo-fourline.png') }}" alt="FOURLINE CONNECT" class="login-logo">
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
    if (btn && campo) btn.addEventListener('click', function () {
        var mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        btn.querySelector('i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
        btn.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
        btn.title = btn.getAttribute('aria-label');
    });

    // Onda de pontos em perspectiva, animada devagar (para em "reduzir movimento").
    var cv = document.getElementById('loginWave');
    if (!cv || !cv.getContext) return;
    var ctx = cv.getContext('2d'), w = 0, h = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
    var parado = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function medir() { var r = cv.getBoundingClientRect(); w = r.width; h = r.height; cv.width = w * dpr; cv.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); }
    // Luzes desfocadas (bokeh) ao fundo, andando devagar.
    var bokeh = [];
    for (var k = 0; k < 22; k++) bokeh.push({ x: Math.random(), y: Math.random() * 0.55, r: 6 + Math.random() * 22, a: 0.05 + Math.random() * 0.12, v: 0.00002 + Math.random() * 0.00004 });
    function quadro(t) {
        ctx.clearRect(0, 0, w, h);
        bokeh.forEach(function (b) {
            var x = ((b.x + t * b.v) % 1.1) * w, g = ctx.createRadialGradient(x, b.y * h, 0, x, b.y * h, b.r);
            g.addColorStop(0, 'rgba(74,222,128,' + b.a + ')'); g.addColorStop(1, 'rgba(74,222,128,0)');
            ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, b.y * h, b.r, 0, 6.283); ctx.fill();
        });
        // Malha de pontos em perspectiva: fileiras de trás pequenas e apagadas, da frente grandes e com brilho.
        var linhas = 22, cols = 30, horizonte = h * 0.30;
        for (var j = 0; j < linhas; j++) {
            var p = Math.pow(j / (linhas - 1), 1.7), esc = 0.25 + p * 1.9;
            for (var i = 0; i < cols; i++) {
                var u = i / (cols - 1) - 0.5, x = w / 2 + u * w * esc * 1.15;
                var onda = Math.sin(u * 5 + t * 0.0008 + p * 3.2) * 26 + Math.cos(u * 3 - t * 0.0006 + p * 5) * 16;
                var y = horizonte + p * h * 0.72 + onda * (0.3 + p);
                if (x < -20 || x > w + 20 || y > h + 20) continue;
                var a = 0.25 + p * 0.75, raio = 0.9 + p * 3.4, lima = ((i * 7 + j * 3) % 11) === 0;
                ctx.shadowBlur = 4 + p * 12;
                ctx.shadowColor = lima ? 'rgba(168,207,69,.9)' : 'rgba(34,197,94,.9)';
                ctx.fillStyle = lima ? 'rgba(190,230,90,' + a + ')' : 'rgba(52,211,120,' + a + ')';
                ctx.beginPath(); ctx.arc(x, y, raio, 0, 6.283); ctx.fill();
            }
        }
        ctx.shadowBlur = 0;
        if (!parado) requestAnimationFrame(quadro);
    }
    medir(); window.addEventListener('resize', medir);
    requestAnimationFrame(quadro);
})();
</script>
@endpush
@endsection
