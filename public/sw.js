/*
 * Service worker do FOURLINE Connect (PWA).
 *
 * - Páginas: sempre da rede (dados de chamados nunca ficam velhos/em cache).
 *   Sem internet, mostra uma página "Sem conexão".
 * - Arquivos estáticos (CSS/JS/ícones/fontes): cache primeiro, atualizado em
 *   segundo plano — o app abre mais rápido.
 * - Nunca guarda respostas de usuário logado (HTML/JSON) nem requisições POST.
 */
const VERSION = 'fl-v1';
const STATIC = 'fl-static-' + VERSION;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC).then((c) => c.addAll([OFFLINE_URL, '/icons/icon-192.png', '/favicon.svg'])).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== STATIC).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const isStatic = (url) =>
    /\.(?:css|js|mjs|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf)$/i.test(url.pathname) ||
    url.hostname === 'cdn.jsdelivr.net' || url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // Navegação (páginas): rede; offline → página de aviso.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Estáticos: cache primeiro + atualização em segundo plano.
    if (isStatic(url)) {
        event.respondWith(
            caches.open(STATIC).then((cache) =>
                cache.match(req).then((hit) => {
                    const net = fetch(req).then((res) => {
                        if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone());
                        return res;
                    }).catch(() => hit);
                    return hit || net;
                })
            )
        );
    }
    // Demais (APIs/JSON): segue direto para a rede, sem cache.
});
