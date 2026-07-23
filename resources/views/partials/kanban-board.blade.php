@php $cols = ['todo' => 'A fazer', 'doing' => 'Em andamento', 'done' => 'Concluído']; @endphp
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold"><i class="bi {{ $icon }} me-2 {{ $headClass ?? 'text-success' }}"></i>{{ $title }}</span>
        <button class="btn btn-sm {{ $btnClass ?? 'btn-success' }}" onclick="openCard(null, 'todo', '{{ $board }}')"><i class="bi bi-plus-lg me-1"></i> Novo cartão</button>
    </div>
    <div class="card-body">
        <div class="kanban-board">
            @foreach ($cols as $key => $label)
                <div class="kanban-col">
                    <div class="kanban-col-head"><span class="dot dot-{{ $key }}"></span>{{ $label }} <span class="count">{{ ($cards[$key] ?? collect())->count() }}</span></div>
                    <div class="kanban-list" data-status="{{ $key }}" data-board="{{ $board }}">
                        @foreach (($cards[$key] ?? collect()) as $c)
                            @php $overdue = $c->due_date && $c->status !== 'done' && $c->due_date->lt(today()); @endphp
                            <div class="kanban-card" data-id="{{ $c->id }}" data-title="{{ $c->title }}"
                                 data-description="{{ $c->description }}" data-assignee="{{ $c->assignee_glpi_id }}"
                                 data-due="{{ optional($c->due_date)->format('Y-m-d') }}" data-color="{{ $c->color }}"
                                 data-board="{{ $board }}"
                                 data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="kanban-tip"
                                 title="{{ $c->description ?: 'Sem descrição adicional.' }}"
                                 onclick="openCard(this)">
                                @if ($c->color)<span class="kc-color" style="background: {{ $c->color }}"></span>@endif
                                <div class="kc-title">{{ $c->title }}</div>
                                <div class="kc-meta">
                                    @if ($c->assignee_name)<span><i class="bi bi-person"></i> {{ $c->assignee_name }}</span>@endif
                                    @if ($c->due_date)<span class="{{ $overdue ? 'text-danger fw-semibold' : '' }}"><i class="bi bi-calendar-event"></i> {{ $c->due_date->format('d/m/Y') }}@if ($overdue) (vencido)@endif</span>@endif
                                </div>
                            </div>
                        @endforeach
                        <div class="kanban-empty">Solte cartões aqui</div>
                    </div>
                    <button type="button" class="kanban-add" onclick="openCard(null, '{{ $key }}', '{{ $board }}')"><i class="bi bi-plus-lg"></i> Adicionar cartão</button>
                </div>
            @endforeach
        </div>
    </div>
</div>
