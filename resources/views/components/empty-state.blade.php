{{-- Tela/lista vazia com orientação: ícone, título, texto e ação opcional (slot).
     Uso: <x-empty-state icon="bi-inbox" title="Nada aqui" text="..."> <a class="btn ...">Criar</a> </x-empty-state> --}}
@props(['icon' => 'bi-inbox', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <span class="empty-state-ic" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
    <div class="empty-state-title">{{ $title }}</div>
    @if ($text)<p class="empty-state-text">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())
        <div class="empty-state-actions">{{ $slot }}</div>
    @endif
</div>
