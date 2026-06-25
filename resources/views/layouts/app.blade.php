<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FOURLINE — Central de Chamados')</title>
    <script>
        document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'light');
        if (localStorage.getItem('sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed');
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@auth
    @php $u = auth()->user(); @endphp
    <div class="app-shell">
        <div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
        <aside class="sidebar">
            <div class="sidebar-brand">
                <img class="brand-full" src="{{ asset('logo-fourline-white.png') }}" alt="FOURLINE CONNECT">
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
            <div class="dropdown dropup sidebar-user-wrap">
                <a href="#" class="sidebar-user dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar">{{ mb_substr($u->name, 0, 1) }}</span>
                    <span class="info">
                        <span class="name">{{ $u->name }}</span>
                        <span class="role">{{ $u->role->label() }}</span>
                    </span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <ul class="dropdown-menu w-100">
                    <li class="dropdown-header small text-truncate">{{ $u->email }}</li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-1"></i> Sair</button>
                        </form>
                    </li>
                </ul>
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
                    <div class="dropdown">
                        <button class="theme-toggle position-relative" data-bs-toggle="dropdown" title="Notificações" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <span class="notif-dot"></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width: 280px">
                            <li class="dropdown-header">Notificações</li>
                            <li><hr class="dropdown-divider"></li>
                            <li class="px-2 py-2 text-muted small">Em construção — você será avisado por aqui sobre atualizações dos seus chamados.</li>
                        </ul>
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
@stack('scripts')
</body>
</html>
