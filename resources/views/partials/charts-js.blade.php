{{-- Chart.js + tema único da FOURLINE (public/js/fl-charts.js). Incluir onde houver gráfico. --}}
@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script src="{{ asset('js/fl-charts.js') }}?v={{ @filemtime(public_path('js/fl-charts.js')) ?: '1' }}"></script>
    @endpush
@endonce
