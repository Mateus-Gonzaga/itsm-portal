{{-- Cabeçalho padrão das telas: ícone da marca, título, subtítulo e ações à direita (slot).
     Uso: <x-page-header icon="bi-people" title="Clientes" subtitle="..."> botões </x-page-header> --}}
@props(['icon' => null, 'title', 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'page-header']) }}>
    <div class="page-header-main">
        @if ($icon)<span class="page-header-ic" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>@endif
        <div class="min-w-0">
            <h1 class="page-title">{{ $title }}</h1>
            @if (isset($sub) && $sub->isNotEmpty())
                <p class="page-subtitle">{{ $sub }}</p>
            @elseif ($subtitle)
                <p class="page-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if ($slot->isNotEmpty())
        <div class="page-header-actions">{{ $slot }}</div>
    @endif
</div>
