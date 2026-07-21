@extends('layouts.app')

@section('title', 'Mapa de Clientes — FOURLINE Connect')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    #clientMap { height: 72vh; min-height: 420px; border-radius: 12px; z-index: 0; }
    .map-legend { font-size: .85rem; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Mapa de Clientes</h1>
        <p class="text-secondary small mb-0">Localização dos clientes/vizinhos no mapa.</p>
    </div>
    <a href="{{ route('modules.clients') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar para Clientes</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-light border small d-flex align-items-center gap-2 mb-3" role="alert">
            <i class="bi bi-info-circle text-success fs-5"></i>
            <div>Base do mapa pronta. Os <strong>pins dos clientes</strong> serão adicionados aqui conforme cadastrarmos as localizações (endereço/coordenadas).</div>
        </div>
        <div id="clientMap"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    var el = document.getElementById('clientMap');
    if (!el || !window.L) return;

    // Centro em Brasília/DF (ajustamos conforme os clientes reais).
    var map = L.map('clientMap').setView([-15.793889, -47.882778], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    // Localizações dos clientes (por enquanto vazio — plotaremos aqui).
    // Formato: { name: 'Cliente', lat: -15.79, lng: -47.88 }
    var LOCATIONS = @json($locations ?? []);
    var bounds = [];
    LOCATIONS.forEach(function (c) {
        if (c.lat == null || c.lng == null) return;
        L.marker([c.lat, c.lng]).addTo(map).bindPopup('<strong>' + (c.name || 'Cliente') + '</strong>');
        bounds.push([c.lat, c.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [40, 40] });

    // Corrige render dentro de card/aba.
    setTimeout(function () { map.invalidateSize(); }, 200);
})();
</script>
@endpush
