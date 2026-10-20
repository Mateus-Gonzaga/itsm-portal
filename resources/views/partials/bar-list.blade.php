{{--
    Lista de barras horizontais padrão (Painel, Relatórios…).
    $rows: [['nome' => 'Drogacei', 'total' => 29], ...]   $link (opcional): fn($nome) => url
--}}
@php $max = max(1, collect($rows)->max('total') ?? 1); @endphp
@forelse ($rows as $r)
    <div class="bar-row">
        <span class="name text-truncate" title="{{ $r['nome'] }}">
            @isset($link)<a href="{{ $link($r['nome']) }}" class="text-reset text-decoration-none">{{ $r['nome'] }}</a>@else{{ $r['nome'] }}@endisset
        </span>
        <span class="bar-track"><span class="bar-fill" style="width: {{ round($r['total'] / $max * 100) }}%"></span></span>
        <span class="num">{{ $r['total'] }}</span>
    </div>
@empty
    <p class="text-secondary small mb-0">{{ $empty ?? 'Sem dados no período.' }}</p>
@endforelse
