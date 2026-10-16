<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    {{-- PWA: instalar como aplicativo no celular/computador --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#033d22">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FOURLINE">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <title>@yield('title', 'FOURLINE — Central de Chamados')</title>
    <script>
        document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'light');
        if (localStorage.getItem('sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed');
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}?v={{ @filemtime(public_path('css/brand.css')) ?: '1' }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@auth
    @php $u = auth()->user(); @endphp
    <div class="app-shell">
        <div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
        <aside class="sidebar">
            <div class="sidebar-brand">
                <img class="brand-full" src="{{ asset('logo-fourline.png') }}" alt="FOURLINE CONNECT">
                <span class="brand-mini">FL</span>
            </div>
            <nav class="sidebar-nav">
                @foreach ($u->role->menu() as $item)
                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}" data-label="{{ $item['label'] }}">
                        <i class="bi {{ $item['icon'] }}"></i> <span class="label">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Chip de usuário --}}
            @php
                // Login do GLPI ("mateus.fourline") vira "Mateus"; nomes com espaço ficam como estão.
                $rawName = trim((string) $u->name);
                $displayName = str_contains($rawName, ' ') || ! str_contains($rawName, '.')
                    ? $rawName
                    : \Illuminate\Support\Str::title(\Illuminate\Support\Str::before($rawName, '.'));
                $parts = preg_split('/[\s._-]+/u', $rawName, -1, PREG_SPLIT_NO_EMPTY) ?: [$rawName];
                $initials = mb_strtoupper(mb_substr($parts[0], 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
                $showEmail = $u->email && ! str_ends_with((string) $u->email, '@glpi.local');
                $roleIcon = match ($u->role->value) { 'gestor' => 'bi-shield-check', 'tecnico' => 'bi-tools', default => 'bi-person' };
            @endphp
            <div class="dropdown dropup sidebar-user-wrap">
                <button type="button" class="sidebar-user" data-bs-toggle="dropdown" data-bs-offset="0,10"
                        aria-expanded="false" aria-label="Menu do usuário {{ $displayName }}" title="{{ $rawName }}">
                    <span class="avatar">{{ $initials }}<span class="presence" aria-hidden="true"></span></span>
                    <span class="info">
                        <span class="name">{{ $displayName }}</span>
                        <span class="role"><i class="bi {{ $roleIcon }}"></i> {{ $u->role->label() }}</span>
                    </span>
                    <i class="bi bi-three-dots-vertical more" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu user-menu">
                    <div class="user-menu-head">
                        <span class="avatar lg">{{ $initials }}</span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $displayName }}</div>
                            <div class="small text-truncate opacity-75">{{ $showEmail ? $u->email : $rawName }}</div>
                            <span class="role-pill"><i class="bi {{ $roleIcon }}"></i> {{ $u->role->label() }}</span>
                        </div>
                    </div>
                    <div class="user-menu-body">
                        <a class="dropdown-item" href="{{ route('modules.settings') }}"><i class="bi bi-gear"></i> Configurações</a>
                        <button type="button" class="dropdown-item" onclick="toggleTheme()"><i class="bi bi-circle-half"></i> Alternar tema claro/escuro</button>
                        <button type="button" class="dropdown-item d-none d-lg-flex" onclick="toggleSidebar()"><i class="bi bi-layout-sidebar-inset"></i> Recolher / expandir menu</button>
                    </div>
                    <div class="user-menu-foot">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger-emphasis logout"><i class="bi bi-box-arrow-right"></i> Sair</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <div class="content">
            <header class="topbar">
                <button class="btn btn-sm border d-lg-none" onclick="document.body.classList.toggle('sidebar-open')">
                    <i class="bi bi-list"></i>
                </button>
                <button class="sidebar-toggle d-none d-lg-inline-flex" onclick="toggleSidebar()" title="Recolher / expandir menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema claro/escuro">
                        <i class="bi bi-circle-half"></i>
                    </button>
                    <div class="dropdown" id="notifWrap">
                        <button class="theme-toggle position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notificações" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <span class="notif-dot d-none" id="notifDot"></span>
                            <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle d-none" id="notifCount" style="font-size:.62rem">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 320px; max-width: 340px">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                                <span class="fw-semibold small">Notificações</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none d-none" id="notifMarkRead">Marcar como lidas</button>
                            </div>
                            <div id="notifList" style="max-height: 360px; overflow-y: auto">
                                <div class="px-3 py-4 text-center text-muted small">Carregando…</div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="page">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1"></i> {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
@else
    <div class="auth-wrap">
        <div class="w-100" style="max-width: 420px">
            @yield('content')
        </div>
    </div>
@endauth

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleTheme() {
        var el = document.documentElement;
        var next = el.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        el.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
    }
    function toggleSidebar() {
        var collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'expanded');
    }
</script>
@auth
<style>.notif-item:hover { background: rgba(6,138,79,.08); }</style>
<script>
(function () {
    const wrap = document.getElementById('notifWrap');
    if (!wrap) return;
    const listEl = document.getElementById('notifList');
    const countEl = document.getElementById('notifCount');
    const dotEl = document.getElementById('notifDot');
    const markBtn = document.getElementById('notifMarkRead');
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const INDEX_URL = "{{ route('notifications.index') }}";
    const READ_URL = "{{ route('notifications.read') }}";

    function esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }

    function render(data) {
        const n = data.count || 0;
        if (n > 0) {
            countEl.textContent = n > 9 ? '9+' : n;
            countEl.classList.remove('d-none');
            dotEl.classList.remove('d-none');
            markBtn.classList.remove('d-none');
        } else {
            countEl.classList.add('d-none');
            dotEl.classList.add('d-none');
            markBtn.classList.add('d-none');
        }
        if (!data.items || !data.items.length) {
            listEl.innerHTML = '<div class="px-3 py-4 text-center text-muted small"><i class="bi bi-check2-all d-block fs-5 mb-1"></i>Nenhuma novidade.</div>';
            return;
        }
        listEl.innerHTML = data.items.map(function (i) {
            return '<a href="' + i.url + '" class="d-flex gap-2 px-3 py-2 text-decoration-none border-bottom notif-item">'
                + '<i class="bi ' + esc(i.icon) + ' text-success mt-1"></i>'
                + '<span class="flex-grow-1 overflow-hidden"><span class="d-block small fw-semibold text-body">' + esc(i.title) + '</span>'
                + '<span class="d-block small text-secondary text-truncate">' + esc(i.detail) + '</span>'
                + '<span class="d-block text-muted" style="font-size:.72rem">' + esc(i.when) + '</span></span></a>';
        }).join('');
    }

    function load() {
        fetch(INDEX_URL, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d) render(d); })
            .catch(function () {});
    }

    markBtn.addEventListener('click', function () {
        fetch(READ_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
            .then(function () { load(); });
    });

    load();
    setInterval(load, 60000);
})();
</script>
@endauth
@stack('scripts')
<script>
    // PWA: o navegador só ativa o service worker em HTTPS (ou localhost).
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
    }
</script>
</body>
</html>
