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
    <title>@yield('title', 'FOURLINE Connect')</title>
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
                @php $grupoAtual = null; @endphp
                @foreach ($u->role->menu() as $item)
                    @if (($item['group'] ?? null) && $item['group'] !== $grupoAtual)
                        @php $grupoAtual = $item['group']; @endphp
                        <div class="nav-group {{ $loop->first ? 'first' : '' }}" role="presentation">{{ $grupoAtual }}</div>
                    @endif
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
                {{-- strategy fixed: o menu abre por cima da página; a sidebar (overflow oculto) cortava o menu quando recolhida --}}
                <button type="button" class="sidebar-user" data-bs-toggle="dropdown" data-bs-offset="0,10"
                        data-bs-popper-config='{"strategy":"fixed"}'
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
                        <button type="button" class="dropdown-item" onclick="window.abrirNovidades && abrirNovidades()"><i class="bi bi-stars"></i> Novidades do portal</button>
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
                @if ($u->role->isStaff())
                    <button type="button" class="global-search-btn" onclick="abrirBusca()" aria-label="Buscar chamado, cliente ou equipamento (Ctrl+K)">
                        <i class="bi bi-search"></i>
                        <span class="d-none d-md-inline text-truncate">Buscar chamado, cliente ou equipamento…</span>
                        <kbd class="d-none d-md-inline">Ctrl K</kbd>
                    </button>
                @endif
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
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="d-flex gap-2 px-3 py-2 border-bottom" aria-hidden="true">
                                        <span class="skeleton" style="width:18px;height:18px;border-radius:50%"></span>
                                        <span class="flex-grow-1"><span class="skeleton skeleton-line" style="width:70%"></span><span class="skeleton skeleton-line" style="width:45%"></span></span>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="page">
                @yield('content')
            </main>

            {{-- Avisos flutuantes (salvo / erro): somem sozinhos; o de erro fica mais tempo. --}}
            <div class="toast-container position-fixed bottom-0 end-0 p-3 fl-toasts" id="flToasts" aria-live="polite">
                @foreach (['status' => 'success', 'error' => 'danger'] as $chave => $tom)
                    @if (session($chave))
                        <div class="toast fl-toast fl-toast-{{ $tom }}" role="{{ $tom === 'danger' ? 'alert' : 'status' }}"
                             data-bs-delay="{{ $tom === 'danger' ? 9000 : 5000 }}">
                            <div class="d-flex align-items-start gap-2 p-3">
                                <i class="bi {{ $tom === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill' }} fl-toast-ic"></i>
                                <div class="flex-grow-1 small">{{ session($chave) }}</div>
                                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast" aria-label="Fechar"></button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if ($u->role->isStaff())
            {{-- Busca global (Ctrl+K) --}}
            <div class="modal fade" id="buscaModal" tabindex="-1" aria-label="Busca global" aria-hidden="true">
                <div class="modal-dialog modal-lg busca-dialog">
                    <div class="modal-content busca">
                        <div class="busca-input">
                            <i class="bi bi-search"></i>
                            <input type="search" id="buscaQ" class="form-control border-0 shadow-none" autocomplete="off"
                                   placeholder="Nº do chamado, título, cliente, equipamento ou tela…" aria-label="Termo de busca">
                            <kbd>Esc</kbd>
                        </div>
                        <div class="busca-resultados" id="buscaRes" role="listbox">
                            <div class="busca-dica">Digite ao menos 2 letras, ou o número do chamado.</div>
                        </div>
                        <div class="busca-rodape"><span><kbd>↑</kbd><kbd>↓</kbd> navegar</span><span><kbd>Enter</kbd> abrir</span><span><kbd>Ctrl</kbd><kbd>K</kbd> abrir a busca</span></div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@else
    <div class="auth-wrap">
        <div class="auth-glow" aria-hidden="true"></div>
        <div class="w-100 position-relative" style="max-width: 420px">
            @yield('content')
        </div>
        <div class="auth-foot">© {{ date('Y') }} FOURLINE · Suporte e infraestrutura de TI</div>
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
@include('partials.novidades')
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

// Avisos flutuantes (toasts) vindos do servidor.
document.querySelectorAll('#flToasts .toast').forEach(function (t) { bootstrap.Toast.getOrCreateInstance(t).show(); });

// Menu recolhido: nome do item ao passar o mouse, flutuando fora da sidebar
// (um ::after dentro dela era cortado pelo overflow oculto).
(function () {
    var tip = document.createElement('div');
    tip.className = 'nav-tip';
    tip.setAttribute('aria-hidden', 'true');
    document.body.appendChild(tip);
    var esconder = function () { tip.classList.remove('show'); };
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(function (a) {
        a.addEventListener('mouseenter', function () {
            if (!document.documentElement.classList.contains('sidebar-collapsed') || window.innerWidth < 992) return;
            var r = a.getBoundingClientRect();
            tip.textContent = a.getAttribute('data-label') || '';
            tip.style.left = (r.right + 10) + 'px';
            tip.style.top = (r.top + r.height / 2) + 'px';
            tip.classList.add('show');
        });
        a.addEventListener('mouseleave', esconder);
        a.addEventListener('click', esconder);
    });
    document.querySelector('.sidebar-nav') && document.querySelector('.sidebar-nav').addEventListener('scroll', esconder);
})();

// Tabelas em cartões no celular: cada célula recebe o nome da coluna (usado pelo CSS).
document.querySelectorAll('table.table-cards').forEach(function (tb) {
    var heads = Array.from(tb.querySelectorAll('thead th')).map(function (th) { return th.textContent.trim(); });
    tb.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.from(tr.children).forEach(function (td, i) {
            if (!td.hasAttribute('colspan') && heads[i]) td.setAttribute('data-label', heads[i]);
        });
    });
});
</script>
@if ($u->role->isStaff())
<script>
(function () {
    const modalEl = document.getElementById('buscaModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const input = document.getElementById('buscaQ');
    const box = document.getElementById('buscaRes');
    const URL_BUSCA = "{{ route('search') }}";
    let timer = null, ctrl = null, ativo = -1;

    function esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    function itens() { return Array.from(box.querySelectorAll('.busca-item')); }
    function marcar(i) {
        const lista = itens();
        lista.forEach(function (el, k) { el.classList.toggle('active', k === i); el.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
        ativo = i;
        if (lista[i]) lista[i].scrollIntoView({ block: 'nearest' });
    }
    function esqueleto() {
        box.innerHTML = [1, 2, 3, 4].map(function () {
            return '<div class="busca-item" aria-hidden="true"><span class="skeleton" style="width:30px;height:30px;border-radius:8px"></span>'
                + '<span class="flex-grow-1"><span class="skeleton skeleton-line" style="width:60%"></span><span class="skeleton skeleton-line" style="width:35%"></span></span></div>';
        }).join('');
    }
    function render(data, termo) {
        const grupos = (data && data.grupos) || [];
        if (!grupos.length) {
            box.innerHTML = '<div class="busca-dica"><i class="bi bi-search d-block fs-3 mb-1 opacity-50"></i>Nada encontrado para “' + esc(termo) + '”.</div>';
            ativo = -1; return;
        }
        box.innerHTML = grupos.map(function (g) {
            return '<div class="busca-grupo">' + esc(g.nome) + '</div>' + g.itens.map(function (i) {
                return '<a class="busca-item" role="option" href="' + esc(i.url) + '">'
                    + '<span class="busca-item-ic"><i class="bi ' + esc(i.icone) + '"></i></span>'
                    + '<span class="min-w-0 flex-grow-1"><span class="d-block text-truncate fw-semibold">' + esc(i.titulo) + '</span>'
                    + '<span class="d-block text-truncate small text-secondary">' + esc(i.detalhe) + '</span></span>'
                    + '<i class="bi bi-arrow-return-left busca-enter"></i></a>';
            }).join('');
        }).join('');
        marcar(0);
    }
    function buscar() {
        const termo = input.value.trim();
        if (termo.length < 2 && !/^#?\d+$/.test(termo)) {
            box.innerHTML = '<div class="busca-dica">Digite ao menos 2 letras, ou o número do chamado.</div>';
            ativo = -1; return;
        }
        esqueleto();
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        fetch(URL_BUSCA + '?q=' + encodeURIComponent(termo), { headers: { 'Accept': 'application/json' }, signal: ctrl.signal })
            .then(function (r) { return r.ok ? r.json() : { grupos: [] }; })
            .then(function (d) { render(d, termo); })
            .catch(function (e) { if (e.name !== 'AbortError') box.innerHTML = '<div class="busca-dica text-danger">Não foi possível buscar agora. Tente de novo.</div>'; });
    }

    window.abrirBusca = function () { modal.show(); };
    modalEl.addEventListener('shown.bs.modal', function () { input.focus(); input.select(); });
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(buscar, 250); });
    input.addEventListener('keydown', function (e) {
        const lista = itens();
        if (e.key === 'ArrowDown' && lista.length) { e.preventDefault(); marcar(Math.min(ativo + 1, lista.length - 1)); }
        else if (e.key === 'ArrowUp' && lista.length) { e.preventDefault(); marcar(Math.max(ativo - 1, 0)); }
        else if (e.key === 'Enter' && lista[ativo]) { e.preventDefault(); location.href = lista[ativo].href; }
    });
    document.addEventListener('keydown', function (e) {
        const tag = (e.target.tagName || '').toLowerCase();
        const digitando = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); modal.show(); }
        else if (e.key === '/' && !digitando) { e.preventDefault(); modal.show(); }
    });
})();
</script>
@endif
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
