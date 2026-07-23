@extends('layouts.app')

@section('title', 'Agenda — FOURLINE Connect')

@push('head')
<style>
    .agenda-toolbar { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; }
    .agenda-legend { display:flex; gap:1rem; align-items:center; font-size:.82rem; color:var(--bs-secondary-color); }
    .agenda-legend .dot { display:inline-block; width:.85rem; height:.85rem; border-radius:4px; margin-right:.35rem; vertical-align:middle; }
    .dot-task { background:linear-gradient(135deg,#0a9d5a,#067a45); }
    .dot-done { background:#aeb4ba; }
    .dot-sla  { background:#f59e0b; }
    .dot-event { background:linear-gradient(135deg,#6366f1,#4338ca); }

    /* ---- FullCalendar com a cara da marca ---- */
    .fc {
        --fc-border-color: var(--bs-border-color);
        --fc-page-bg-color: transparent;
        --fc-button-bg-color:#068A4F; --fc-button-border-color:#068A4F;
        --fc-button-hover-bg-color:#04713f; --fc-button-hover-border-color:#04713f;
        --fc-button-active-bg-color:#A8CF45; --fc-button-active-border-color:#A8CF45;
        --fc-today-bg-color:rgba(168,207,69,.16);
        --fc-neutral-bg-color:rgba(0,0,0,.02);
        font-size:.95rem;
    }
    .fc .fc-button { font-weight:600; text-transform:capitalize; box-shadow:none !important; border-radius:8px; }
    .fc .fc-button-group > .fc-button { border-radius:0; }
    .fc .fc-button-group > .fc-button:first-child { border-top-left-radius:8px; border-bottom-left-radius:8px; }
    .fc .fc-button-group > .fc-button:last-child { border-top-right-radius:8px; border-bottom-right-radius:8px; }
    .fc .fc-button-active { color:#033d22 !important; }
    /* Botão de ação em destaque (lime), separado dos botões de visão */
    .fc .fc-novo-button { background:#A8CF45 !important; border-color:#A8CF45 !important; color:#033d22 !important; font-weight:700; margin-right:.6rem !important; border-radius:8px !important; box-shadow:0 2px 6px rgba(168,207,69,.45) !important; }
    .fc .fc-novo-button:hover { background:#97bd3a !important; border-color:#97bd3a !important; color:#033d22 !important; }
    /* Botão "Nova tarefa" (índigo, igual à tarefa livre) */
    .fc .fc-novaTarefa-button { background:#4338ca !important; border-color:#4338ca !important; color:#fff !important; font-weight:700; border-radius:8px !important; box-shadow:0 2px 6px rgba(67,56,202,.4) !important; }
    .fc .fc-novaTarefa-button:hover { background:#3730a3 !important; border-color:#3730a3 !important; color:#fff !important; }
    .fc .fc-toolbar.fc-header-toolbar { margin-bottom:1.2rem; gap:.6rem; flex-wrap:wrap; }

    .agenda-card .card-header { background:transparent; border-bottom:1px solid var(--bs-border-color); }
    .fc .fc-toolbar-title { font-family:'Rajdhani',sans-serif; font-size:1.5rem; font-weight:700; }
    .fc .fc-col-header-cell-cushion { font-weight:700; color:var(--bs-secondary-color); text-transform:uppercase; font-size:.74rem; letter-spacing:.04em; padding:.6rem .5rem; }
    .fc .fc-daygrid-day-number { font-size:.85rem; font-weight:600; color:var(--bs-body-color); padding:.35rem .5rem; }
    .fc .fc-day-today .fc-daygrid-day-number { color:#067a45; font-weight:800; }

    /* Células do mês mais altas + fins de semana destacados (mais legível) */
    .fc .fc-daygrid-day-frame { min-height:104px; }
    .fc-theme-standard .fc-scrollgrid { border-radius:10px; overflow:hidden; }
    .fc .fc-day-sat, .fc .fc-day-sun { background:rgba(6,138,79,.035); }
    [data-bs-theme="dark"] .fc .fc-day-sat, [data-bs-theme="dark"] .fc .fc-day-sun { background:rgba(255,255,255,.03); }
    .fc .fc-daygrid-more-link { font-weight:600; color:#067a45; }

    /* ---- Eventos (bloco) ---- */
    .fc-daygrid-event, .fc-timegrid-event { border:none !important; background:transparent !important; cursor:pointer; box-shadow:none !important; }
    .fc-daygrid-event { margin-top:3px !important; }
    .ev-inner { display:flex; align-items:center; gap:.4rem; padding:4px 9px; border-radius:8px; overflow:hidden; font-size:.84rem; line-height:1.45; min-height:26px; }
    .ev-inner .bi { flex:0 0 auto; font-size:.85rem; }
    .ev-time  { font-weight:700; font-size:.74rem; opacity:.95; flex:0 0 auto; }
    .ev-title { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ev-tech  { margin-left:auto; font-size:.7rem; font-weight:600; opacity:.95; background:rgba(255,255,255,.24); padding:.05rem .45rem; border-radius:999px; white-space:nowrap; }

    .ev-task .ev-inner { background:linear-gradient(135deg,#0a9d5a,#067a45); color:#fff; border-left:4px solid #A8CF45; box-shadow:0 2px 7px rgba(3,61,34,.22); }
    .ev-done .ev-inner { background:#9aa1a8; color:#fff; border-left:4px solid #cfd4d9; }
    .ev-done .ev-title { text-decoration:line-through; opacity:.92; }
    .ev-sla  .ev-inner { background:rgba(245,158,11,.18); color:#9a5b08; border-left:4px solid #f59e0b; font-weight:700; }
    [data-bs-theme="dark"] .ev-sla .ev-inner { color:#fbbf24; background:rgba(245,158,11,.2); }
    .ev-sla .ev-tech { display:none; }
    .ev-event .ev-inner { background:linear-gradient(135deg,#6366f1,#4338ca); color:#fff; border-left:4px solid #c7d2fe; box-shadow:0 2px 7px rgba(49,46,129,.22); }
    .fc-event:hover .ev-inner { filter:brightness(1.05); transform:translateY(-1px); transition:.12s; box-shadow:0 4px 10px rgba(3,61,34,.28); }
    .fc-daygrid-event .ev-tech { display:none; } /* mês: economiza espaço */
    .fc-timegrid-event .ev-tech { display:inline-block; }

    /* ---- Visão em Lista ---- */
    .fc .fc-list { border-radius:10px; overflow:hidden; }
    .fc .fc-list-day-cushion { background:rgba(6,138,79,.08); font-weight:700; }
    [data-bs-theme="dark"] .fc .fc-list-day-cushion { background:rgba(6,138,79,.18); }
    .fc .fc-list-event:hover td { background:rgba(168,207,69,.14); }
    .fc .fc-list-event-dot { border-color:#067a45; border-width:6px; }
    .fc-list-event.ev-sla .fc-list-event-dot { border-color:#f59e0b; }
    .fc-list-event.ev-event .fc-list-event-dot { border-color:#4338ca; }
    .fc-list-event.ev-done .fc-list-event-dot { border-color:#9aa1a8; }
    .fc-list-event.ev-done .fc-list-event-title { text-decoration:line-through; opacity:.8; }
    .fc .fc-list-event-title .bi { color:var(--bs-secondary-color); margin-right:.3rem; }

    /* Prévia da tarefa no hover: cartão claro, largo e legível */
    .agenda-tip {
        --bs-tooltip-bg: var(--bs-body-bg);
        --bs-tooltip-color: var(--bs-body-color);
        --bs-tooltip-max-width: 360px;
        --bs-tooltip-opacity: 1;
        --bs-tooltip-border-radius: 12px;
        --bs-tooltip-padding-x: 0;
        --bs-tooltip-padding-y: 0;
        --bs-tooltip-font-size: .84rem;
    }
    .agenda-tip .tooltip-inner {
        text-align:left; max-width:360px; overflow:hidden;
        border:1px solid var(--bs-border-color);
        box-shadow:0 12px 28px rgba(0,0,0,.2);
    }
    .agenda-tip .tip-head { padding:.6rem .8rem; border-bottom:1px solid var(--bs-border-color); }
    .agenda-tip .tip-title { font-weight:700; font-size:.9rem; line-height:1.3; }
    .agenda-tip .tip-meta { font-size:.75rem; color:var(--bs-secondary-color); margin-top:.15rem; }
    .agenda-tip .tip-meta .bi { margin-right:.2rem; }
    .agenda-tip .tip-desc { padding:.6rem .8rem; white-space:pre-line; line-height:1.55; max-height:240px; overflow-y:auto; }
    .agenda-tip .tip-empty { padding:.55rem .8rem; font-size:.78rem; color:var(--bs-secondary-color); font-style:italic; }
    .agenda-tip .tip-bar { height:4px; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <h1 class="h4 mb-0">Agenda</h1>
    <p class="text-secondary small mb-0">Tarefas dos chamados, prazos de atendimento e tarefas livres da equipe. Clique num dia para lançar uma tarefa.</p>
</div>

{{-- ===================== QUADRO KANBAN DA EQUIPE ===================== --}}
<style>
    .kanban-board { display:flex; gap:1rem; overflow-x:auto; padding-bottom:.5rem; align-items:flex-start; }
    .kanban-col { flex:1 1 0; min-width:300px; background:var(--bs-secondary-bg); border-radius:14px; padding:.85rem; display:flex; flex-direction:column; }
    .kanban-col-head { font-weight:700; font-size:.9rem; padding:.25rem .5rem .6rem; display:flex; align-items:center; gap:.5rem; color:var(--bs-body-color); }
    .kanban-col-head .count { margin-left:auto; font-size:.72rem; font-weight:600; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:999px; padding:0 .5rem; }
    .kanban-col-head .dot { width:.7rem; height:.7rem; border-radius:50%; flex:0 0 auto; }
    .dot-todo { background:#94a3b8; } .dot-doing { background:#f59e0b; } .dot-done { background:#0a9d5a; }
    .kanban-list { min-height:120px; display:flex; flex-direction:column; gap:.6rem; }
    .kanban-card { position:relative; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; padding:.8rem .9rem .8rem 1rem; cursor:grab; box-shadow:0 1px 3px rgba(0,0,0,.06); transition:border-color .1s, transform .05s; }
    .kanban-card:hover { border-color:#A8CF45; }
    .kanban-card:active { cursor:grabbing; }
    .kanban-card .kc-color { position:absolute; left:0; top:0; bottom:0; width:5px; border-radius:12px 0 0 12px; }
    .kanban-card .kc-title { font-weight:600; font-size:1rem; margin-bottom:.35rem; }
    .kanban-card .kc-meta { display:flex; flex-wrap:wrap; gap:.4rem .8rem; font-size:.8rem; color:var(--bs-secondary-color); }
    .kanban-card.sortable-ghost { opacity:.35; }
    .kanban-card.sortable-chosen { border-color:#A8CF45; box-shadow:0 4px 12px rgba(3,61,34,.18); }
    .kanban-empty { text-align:center; color:var(--bs-secondary-color); font-size:.78rem; padding:.9rem; border:1px dashed var(--bs-border-color); border-radius:8px; }
    .kanban-list:has(.kanban-card) .kanban-empty { display:none; }
    .kanban-add { margin-top:.5rem; width:100%; border:none; background:transparent; color:var(--bs-secondary-color); font-size:.84rem; font-weight:600; padding:.5rem; border-radius:8px; text-align:left; cursor:pointer; transition:.1s; }
    .kanban-add:hover { background:var(--bs-body-bg); color:#067a45; }

    /* Tooltip do cartão com cara de cartão (claro, largo, texto à esquerda) */
    .kanban-tip {
        --bs-tooltip-bg: var(--bs-body-bg);
        --bs-tooltip-color: var(--bs-body-color);
        --bs-tooltip-max-width: 320px;
        --bs-tooltip-opacity: 1;
        --bs-tooltip-border-radius: 12px;
        --bs-tooltip-padding-x: .9rem;
        --bs-tooltip-padding-y: .75rem;
        --bs-tooltip-font-size: .84rem;
    }
    .kanban-tip .tooltip-inner {
        text-align:left; line-height:1.5; white-space:pre-line;
        border:1px solid var(--bs-border-color);
        box-shadow:0 10px 26px rgba(0,0,0,.18);
    }
</style>

<p class="text-secondary small mb-0 mt-4"><i class="bi bi-info-circle me-1"></i>Clique em <strong>+ Adicionar cartão</strong> numa coluna. Arraste os cartões entre as colunas — salva sozinho.</p>
@include('partials.kanban-board', ['board' => 'equipe', 'title' => 'Quadro da equipe', 'icon' => 'bi-kanban', 'headClass' => 'text-success', 'btnClass' => 'btn-success', 'cards' => $kanban])
@include('partials.kanban-board', ['board' => 'urgente', 'title' => 'Atenção / Urgente', 'icon' => 'bi-exclamation-triangle-fill', 'headClass' => 'text-danger', 'btnClass' => 'btn-danger', 'cards' => $kanbanUrgente])

<div class="card agenda-card mt-4">
    <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between py-3">
        <div class="agenda-toolbar">
            @if ($canFilter && $technicians->isNotEmpty())
                <div class="input-group input-group-sm" style="width:auto">
                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                    <select id="filterTech" class="form-select" style="min-width:170px">
                        <option value="">Todos os técnicos</option>
                        @foreach ($technicians as $t)
                            <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="toggleSla" checked>
                <label class="form-check-label small" for="toggleSla">Prazos (SLA)</label>
            </div>
        </div>
        <div class="agenda-legend">
            <span><span class="dot dot-task"></span>Chamado</span>
            <span><span class="dot dot-event"></span>Tarefa livre</span>
            <span><span class="dot dot-done"></span>Concluída</span>
            <span><span class="dot dot-sla"></span>Prazo</span>
        </div>
    </div>
    <div class="card-body">
        @if ($events->isEmpty())
            <div class="alert alert-light border d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-calendar2-week fs-5 text-success"></i>
                <div class="small mb-0">Nenhum agendamento ainda. Clique em <strong>Novo agendamento</strong> (ou em um dia do calendário) para criar o primeiro.</div>
            </div>
        @endif
        <div id="calendar"></div>
    </div>
</div>

{{-- Modal: cartão do Kanban --}}
<div class="modal fade" id="cardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="cardForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="card_method" value="POST">
                <input type="hidden" name="status" id="card_status" value="todo">
                <input type="hidden" name="board" id="card_board" value="equipe">
                <div class="modal-header">
                    <h5 class="modal-title" id="card_title_h">Novo cartão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" id="card_title" class="form-control" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição <span class="text-muted small">— opcional</span></label>
                        <textarea name="description" id="card_desc" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsável <span class="text-muted small">— opcional</span></label>
                            <select name="assignee_glpi_id" id="card_assignee" class="form-select">
                                <option value="">— sem responsável —</option>
                                @foreach ($technicianUsers as $t)
                                    <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prazo <span class="text-muted small">— opcional</span></label>
                            <input type="date" name="due_date" id="card_due" class="form-control">
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Etiqueta</label>
                        <div class="d-flex gap-2" id="card_colors">
                            @foreach (['' => 'Nenhuma', '#0a9d5a' => 'Verde', '#f59e0b' => 'Laranja', '#dc3545' => 'Vermelho', '#4338ca' => 'Índigo'] as $hex => $name)
                                <label class="border rounded px-2 py-1 small" style="cursor:pointer">
                                    <input type="radio" name="color" value="{{ $hex }}" class="me-1" @if ($hex === '') checked @endif>
                                    <span style="display:inline-block;width:.8rem;height:.8rem;border-radius:3px;vertical-align:middle;background:{{ $hex ?: 'transparent' }};border:1px solid var(--bs-border-color)"></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger d-none" id="card_delete"><i class="bi bi-trash"></i></button>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Form oculto para excluir cartão --}}
<form id="cardDeleteForm" method="POST" class="d-none">@csrf @method('DELETE')</form>

{{-- Modal: novo agendamento --}}
<div class="modal fade" id="schedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="schedForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Novo agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Chamado</label>
                        <select name="ticket_id" class="form-select" required>
                            <option value="">— selecione —</option>
                            @foreach ($openTickets as $t)
                                <option value="{{ $t['id'] }}">{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                        @if ($openTickets->isEmpty())
                            <div class="form-text text-warning">Nenhum chamado aberto para agendar.</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Técnico</label>
                        @if ($isManager)
                            <select name="technician_glpi_id" class="form-select" required>
                                @foreach ($technicianUsers as $t)
                                    <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="technician_glpi_id" value="{{ $selfTechId }}">
                            <input type="text" class="form-control" value="{{ $selfTechName }} (você)" disabled>
                        @endif
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Início</label>
                            <input type="datetime-local" name="begin" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Fim</label>
                            <input type="datetime-local" name="end" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Descrição <span class="text-muted small">— opcional</span></label>
                        <textarea name="content" rows="2" class="form-control" placeholder="Ex.: visita técnica para troca do equipamento."></textarea>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="schedError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: nova tarefa livre (demanda sem chamado) --}}
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="taskForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskModalTitle"><i class="bi bi-list-task me-2"></i>Nova tarefa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" maxlength="255" required placeholder="Ex.: Instalar antivírus nas máquinas da matriz">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Responsável <span class="text-muted small">— opcional</span></label>
                        <select name="owner_glpi_id" class="form-select">
                            <option value="">— sem responsável (equipe) —</option>
                            @foreach ($technicianUsers as $t)
                                <option value="{{ $t['id'] }}" @if (! $isManager && $t['id'] === $selfTechId) selected @endif>{{ $t['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Início</label>
                            <input type="datetime-local" name="begin" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Fim</label>
                            <input type="datetime-local" name="end" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Detalhes <span class="text-muted small">— opcional</span></label>
                        <textarea name="content" rows="2" class="form-control" placeholder="Observações da tarefa."></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cor</label>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach (['' => 'Padrão', '#4338ca' => 'Índigo', '#0a9d5a' => 'Verde', '#f59e0b' => 'Laranja', '#dc3545' => 'Vermelho', '#0ea5e9' => 'Azul', '#7c3aed' => 'Roxo', '#64748b' => 'Cinza'] as $hex => $nome)
                                <input type="radio" class="btn-check task-color" name="color" id="tc{{ $loop->index }}" value="{{ $hex }}" autocomplete="off" @checked($hex === '')>
                                <label class="btn btn-sm btn-outline-secondary mb-0 d-inline-flex align-items-center gap-1" for="tc{{ $loop->index }}" title="{{ $nome }}">
                                    <span style="display:inline-block;width:.8rem;height:.8rem;border-radius:3px;background:{{ $hex ?: '#4338ca' }};border:1px solid rgba(0,0,0,.15)"></span>
                                    <span class="small">{{ $nome }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2" id="taskRepeatWrap">
                        <input class="form-check-input" type="checkbox" id="taskRepeat">
                        <label class="form-check-label" for="taskRepeat"><i class="bi bi-arrow-repeat me-1"></i>Repetir em vários dias</label>
                    </div>
                    <div class="form-check form-switch mb-2 d-none" id="taskApplySeriesWrap">
                        <input class="form-check-input" type="checkbox" id="taskApplySeries">
                        <label class="form-check-label" for="taskApplySeries">Aplicar a <strong>todos os dias</strong> desta série</label>
                    </div>
                    <div id="taskRepeatBox" class="border rounded p-2 mb-1 bg-body-tertiary d-none">
                        <label class="form-label small mb-1">Repetir nos dias da semana:</label>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach (['0' => 'Dom', '1' => 'Seg', '2' => 'Ter', '3' => 'Qua', '4' => 'Qui', '5' => 'Sex', '6' => 'Sáb'] as $val => $lbl)
                                <input type="checkbox" class="btn-check task-weekday" id="wd{{ $val }}" value="{{ $val }}" autocomplete="off" @checked(in_array($val, ['1', '2', '3', '4', '5']))>
                                <label class="btn btn-sm btn-outline-success mb-0" for="wd{{ $val }}">{{ $lbl }}</label>
                            @endforeach
                        </div>
                        <label class="form-label small mb-1">Repetir até:</label>
                        <input type="date" id="taskUntil" class="form-control form-control-sm">
                    </div>
                    <div class="text-danger small mt-2 d-none" id="taskError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="taskSubmitBtn"><i class="bi bi-check2 me-1"></i> Criar tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: ações de uma tarefa livre --}}
<div class="modal fade" id="taskActionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-list-task me-2"></i>Tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold mb-1" id="taskActionsTitle"></p>
                <p class="small text-secondary mb-2 d-none" id="taskActionsMeta"></p>
                <div class="border rounded bg-body-tertiary p-2 mb-3 small d-none" id="taskActionsDesc"
                     style="white-space:pre-line; max-height:220px; overflow-y:auto"></div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" id="taskEditBtn"><i class="bi bi-pencil me-1"></i> Editar tarefa</button>
                    <button type="button" class="btn btn-success" id="taskDoneBtn"><i class="bi bi-check2-circle me-1"></i> Concluir</button>
                    <button type="button" class="btn btn-outline-danger" id="taskDeleteBtn"><i class="bi bi-trash me-1"></i> Excluir este dia</button>
                    <button type="button" class="btn btn-outline-danger d-none" id="taskDeleteSeriesBtn"><i class="bi bi-trash3 me-1"></i> Excluir todos os dias (série)</button>
                </div>
                <div class="text-danger small mt-2 d-none" id="taskActionsError"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
(function () {
    const ALL_EVENTS = @json($events);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const RESCHEDULE_URL = "{{ route('agenda.reschedule') }}";
    const STORE_URL = "{{ route('agenda.store') }}";
    const EVENT_STORE_URL = "{{ route('agenda.event.store') }}";
    const EVENT_RESCHEDULE_URL = "{{ route('agenda.event.reschedule') }}";
    const EVENT_DONE_URL = "{{ route('agenda.event.done') }}";
    const EVENT_DESTROY_URL = "{{ url('agenda/tarefa') }}"; // + '/' + id
    const EVENT_BASE_URL = "{{ url('agenda/tarefa') }}";    // + '/' + id (PUT = editar)
    const TICKET_URL = "{{ url('tickets') }}";

    let filterTech = '';
    let showSla = true;

    // Escapa texto do usuário (o tooltip usa HTML).
    function esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }

    // "06/07/2026 08:00 – 18:00" (ou só a data, se não tiver hora útil).
    function quando(ev) {
        const d2 = (n) => String(n).padStart(2, '0');
        const i = ev.start, f = ev.end;
        const data = d2(i.getDate()) + '/' + d2(i.getMonth() + 1) + '/' + i.getFullYear();
        const hi = d2(i.getHours()) + ':' + d2(i.getMinutes());
        if (!f) return data + ' ' + hi;
        const mesmoDia = i.toDateString() === f.toDateString();
        const hf = d2(f.getHours()) + ':' + d2(f.getMinutes());
        return mesmoDia ? (data + ' ' + hi + ' – ' + hf) : (data + ' ' + hi);
    }

    // Some com tooltips abertos (evita ficarem "presos" ao arrastar/recarregar).
    function limparTips() { document.querySelectorAll('.tooltip').forEach(function (t) { t.remove(); }); }

    function visibleEvents() {
        return ALL_EVENTS.filter(function (e) {
            if (e.extendedProps.type === 'sla' && !showSla) return false;
            if (filterTech && e.extendedProps.type === 'task' && String(e.extendedProps.technicianId) !== filterTech) return false;
            return true;
        });
    }
    function refresh() {
        calendar.removeAllEvents();
        visibleEvents().forEach(function (e) { calendar.addEvent(e); });
    }
    function persist(info) {
        const p = info.event.extendedProps;
        const begin = info.event.start.toISOString();
        const end = (info.event.end || new Date(info.event.start.getTime() + 3600000)).toISOString();
        let url, body;
        if (p.type === 'task' && p.taskId) {
            url = RESCHEDULE_URL; body = { task_id: p.taskId, begin: begin, end: end };
        } else if (p.type === 'event' && p.eventId) {
            url = EVENT_RESCHEDULE_URL; body = { event_id: p.eventId, begin: begin, end: end };
        } else {
            info.revert(); return;
        }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        }).then(function (r) { if (!r.ok) throw new Error(); })
          .catch(function () { info.revert(); alert('Não foi possível salvar a alteração.'); });
    }

    const el = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(el, {
        locale: 'pt-br',
        firstDay: 1,
        height: 'auto',
        nowIndicator: true,
        eventDisplay: 'block',
        dayMaxEvents: 3,
        initialView: 'dayGridMonth',
        customButtons: {
            novaTarefa: { text: 'Nova tarefa', click: function () { openTaskModal(new Date(), true); } },
            novo: { text: 'Novo agendamento', click: function () { openModal(new Date(), true); } },
        },
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'novaTarefa novo dayGridMonth,timeGridWeek,listMonth' },
        buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia', list: 'Lista' },
        views: { listMonth: { buttonText: 'Lista' } },
        noEventsContent: 'Nenhum agendamento neste período.',
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        editable: true,
        selectable: true,
        eventDrop: persist,
        eventResize: persist,
        eventDragStart: limparTips,
        eventResizeStart: limparTips,
        eventClick: function (info) {
            limparTips();
            const p = info.event.extendedProps;
            if (p.type === 'event') { openTaskActions(info.event); return; }
            if (p.ticketId) window.location = TICKET_URL + '/' + p.ticketId;
        },
        dateClick: function (info) { openTaskModal(info.date, info.allDay); },
        // Prévia da tarefa ao passar o mouse: cartão estilizado (Bootstrap tooltip).
        eventDidMount: function (info) {
            const p = info.event.extendedProps;
            const cor = (p.type === 'event' && p.color) ? p.color
                : (p.type === 'sla' ? '#f59e0b' : (p.type === 'event' ? '#4338ca' : '#067a45'));

            let meta = '<i class="bi bi-calendar-event"></i>' + esc(quando(info.event));
            if (p.technicianName) meta += ' &nbsp;·&nbsp; <i class="bi bi-person"></i>' + esc(p.technicianName);
            if (p.done) meta += ' &nbsp;·&nbsp; <span class="text-success">✓ concluída</span>';

            const corpo = p.description
                ? '<div class="tip-desc">' + esc(p.description) + '</div>'
                : (p.type === 'event' ? '<div class="tip-empty">Sem detalhes. Clique para editar.</div>' : '');

            const html = '<div class="tip-bar" style="background:' + esc(cor) + '"></div>'
                + '<div class="tip-head"><div class="tip-title">' + esc(info.event.title) + '</div>'
                + '<div class="tip-meta">' + meta + '</div></div>' + corpo;

            new bootstrap.Tooltip(info.el, {
                title: html, html: true, placement: 'top', container: 'body',
                customClass: 'agenda-tip', trigger: 'hover',
            });

            // Cor personalizada da tarefa livre.
            if (p.type === 'event' && p.color && !p.done) {
                const inner = info.el.querySelector('.ev-inner');
                if (inner) {
                    inner.style.background = p.color;
                    inner.style.borderLeftColor = 'rgba(255,255,255,.55)';
                }
                const dot = info.el.querySelector('.fc-list-event-dot');
                if (dot) dot.style.borderColor = p.color;
            }
        },
        eventContent: function (arg) {
            const p = arg.event.extendedProps;
            let icon;
            if (p.type === 'sla') icon = 'bi-clock-history';
            else if (p.done) icon = 'bi-check2-circle';
            else if (p.type === 'event') icon = 'bi-list-task';
            else icon = 'bi-tools';
            // Visão Lista: usa o ícone no título e deixa o resto no render nativo.
            if (arg.view.type.indexOf('list') === 0) {
                return { html: '<i class="bi ' + icon + '"></i>' + arg.event.title };
            }
            const wrap = document.createElement('div');
            wrap.className = 'ev-inner';
            let html = '<i class="bi ' + icon + '"></i>';
            if (arg.timeText) html += '<span class="ev-time">' + arg.timeText + '</span>';
            html += '<span class="ev-title">' + arg.event.title + '</span>';
            if ((p.type === 'task' || p.type === 'event') && p.technicianName) html += '<span class="ev-tech">' + p.technicianName + '</span>';
            wrap.innerHTML = html;
            return { domNodes: [wrap] };
        },
        events: visibleEvents(),
    });
    calendar.render();

    const novoBtn = el.querySelector('.fc-novo-button');
    if (novoBtn) { novoBtn.title = 'Agendar um atendimento em um chamado'; }
    const novaTarefaBtn = el.querySelector('.fc-novaTarefa-button');
    if (novaTarefaBtn) { novaTarefaBtn.title = 'Criar uma tarefa/demanda da equipe (sem chamado)'; }

    const sel = document.getElementById('filterTech');
    if (sel) sel.addEventListener('change', function () { filterTech = this.value; refresh(); });
    document.getElementById('toggleSla').addEventListener('change', function () { showSla = this.checked; refresh(); });

    // ---- Modal de novo agendamento ----
    const modalEl = document.getElementById('schedModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('schedForm');
    const errBox = document.getElementById('schedError');

    function fmt(d) { // Date -> value de datetime-local (hora local)
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
    }
    function openModal(date, allDay) {
        const begin = new Date(date);
        if (allDay || (begin.getHours() === 0 && begin.getMinutes() === 0)) begin.setHours(9, 0, 0, 0);
        const end = new Date(begin.getTime() + 3600000);
        form.begin.value = fmt(begin);
        form.end.value = fmt(end);
        errBox.classList.add('d-none');
        modal.show();
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        const payload = {
            ticket_id: form.ticket_id.value,
            technician_glpi_id: form.technician_glpi_id.value,
            begin: new Date(form.begin.value).toISOString(),
            end: new Date(form.end.value).toISOString(),
            content: form.content.value,
        };
        fetch(STORE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (r) {
            if (r.ok) { window.location.reload(); return; }
            return r.json().then(function (j) { throw new Error(j.message || 'Não foi possível agendar.'); });
        }).catch(function (err) {
            errBox.textContent = err.message; errBox.classList.remove('d-none');
        });
    });

    // ---- Modal de nova tarefa livre ----
    const taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
    const taskForm = document.getElementById('taskForm');
    const taskErr = document.getElementById('taskError');

    const taskRepeat = document.getElementById('taskRepeat');
    const taskRepeatBox = document.getElementById('taskRepeatBox');
    const taskUntil = document.getElementById('taskUntil');
    taskRepeat.addEventListener('change', function () { taskRepeatBox.classList.toggle('d-none', !this.checked); });

    let editingId = null; // null = criando; id = editando

    function setColor(hex) {
        const alvo = Array.from(document.querySelectorAll('.task-color'))
            .find(function (r) { return r.value === (hex || ''); });
        if (alvo) alvo.checked = true;
    }

    function openTaskModal(date, allDay) {
        const begin = new Date(date);
        if (allDay || (begin.getHours() === 0 && begin.getMinutes() === 0)) begin.setHours(9, 0, 0, 0);
        const end = new Date(begin.getTime() + 3600000);
        editingId = null;
        taskForm.reset();
        setColor('');
        taskForm.begin.value = fmt(begin);
        taskForm.end.value = fmt(end);
        taskRepeat.checked = false;
        taskRepeatBox.classList.add('d-none');
        taskUntil.value = fmt(begin).slice(0, 10);
        document.getElementById('taskModalTitle').innerHTML = '<i class="bi bi-list-task me-2"></i>Nova tarefa';
        document.getElementById('taskSubmitBtn').innerHTML = '<i class="bi bi-check2 me-1"></i> Criar tarefa';
        document.getElementById('taskRepeatWrap').classList.remove('d-none');
        document.getElementById('taskApplySeriesWrap').classList.add('d-none');
        taskErr.classList.add('d-none');
        taskModal.show();
    }

    // Abre o modal em modo EDIÇÃO, preenchido com a tarefa clicada.
    function openTaskEdit(event) {
        const p = event.extendedProps;
        editingId = p.eventId;
        taskForm.reset();
        taskForm.title.value = event.title || '';
        taskForm.content.value = p.description || '';
        taskForm.owner_glpi_id.value = p.technicianId || '';
        setColor(p.color || '');
        taskForm.begin.value = fmt(event.start);
        taskForm.end.value = fmt(event.end || new Date(event.start.getTime() + 3600000));
        taskRepeat.checked = false;
        taskRepeatBox.classList.add('d-none');
        // Repetir só faz sentido ao criar; ao editar, oferecemos "aplicar à série".
        document.getElementById('taskRepeatWrap').classList.add('d-none');
        const serieWrap = document.getElementById('taskApplySeriesWrap');
        serieWrap.classList.toggle('d-none', !p.seriesId);
        document.getElementById('taskApplySeries').checked = false;
        document.getElementById('taskModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar tarefa';
        document.getElementById('taskSubmitBtn').innerHTML = '<i class="bi bi-check2 me-1"></i> Salvar';
        taskErr.classList.add('d-none');
        taskModal.show();
    }
    taskForm.addEventListener('submit', function (e) {
        e.preventDefault();
        taskErr.classList.add('d-none');
        const ownerSel = taskForm.owner_glpi_id;
        const corSel = document.querySelector('.task-color:checked');
        const payload = {
            title: taskForm.title.value,
            owner_glpi_id: ownerSel.value || null,
            owner_name: ownerSel.value ? ownerSel.options[ownerSel.selectedIndex].text : null,
            begin: new Date(taskForm.begin.value).toISOString(),
            end: new Date(taskForm.end.value).toISOString(),
            content: taskForm.content.value,
            color: corSel && corSel.value ? corSel.value : null,
        };

        let url = EVENT_STORE_URL, method = 'POST';
        if (editingId) { // edição
            url = EVENT_BASE_URL + '/' + editingId;
            method = 'PUT';
            payload.apply_series = document.getElementById('taskApplySeries').checked;
        } else if (taskRepeat.checked && taskUntil.value) {
            payload.repeat = true;
            payload.until = taskUntil.value;
            payload.weekdays = Array.from(document.querySelectorAll('.task-weekday:checked')).map(function (c) { return +c.value; });
        }

        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (r) {
            if (r.ok) { window.location.reload(); return; }
            return r.json().then(function (j) { throw new Error(j.message || 'Não foi possível salvar a tarefa.'); });
        }).catch(function (err) {
            taskErr.textContent = err.message; taskErr.classList.remove('d-none');
        });
    });

    // ---- Modal de ações da tarefa livre (concluir / excluir) ----
    const taskActionsModal = new bootstrap.Modal(document.getElementById('taskActionsModal'));
    const taskActionsErr = document.getElementById('taskActionsError');
    const taskDoneBtn = document.getElementById('taskDoneBtn');
    const taskDeleteBtn = document.getElementById('taskDeleteBtn');
    const taskDeleteSeriesBtn = document.getElementById('taskDeleteSeriesBtn');
    let currentEvent = null;

    function openTaskActions(event) {
        currentEvent = event;
        const p = event.extendedProps;
        const done = p.done;
        document.getElementById('taskActionsTitle').textContent = event.title;

        // Responsável + data/hora
        const metaEl = document.getElementById('taskActionsMeta');
        const partes = [];
        if (p.technicianName) partes.push('👤 ' + p.technicianName);
        if (event.start) {
            const d = event.start;
            const p2 = (n) => String(n).padStart(2, '0');
            partes.push('📅 ' + p2(d.getDate()) + '/' + p2(d.getMonth() + 1) + '/' + d.getFullYear() +
                ' às ' + p2(d.getHours()) + ':' + p2(d.getMinutes()));
        }
        metaEl.textContent = partes.join('   ');
        metaEl.classList.toggle('d-none', partes.length === 0);

        // Descrição (detalhes) — respeita quebras de linha
        const descEl = document.getElementById('taskActionsDesc');
        descEl.textContent = p.description || '';
        descEl.classList.toggle('d-none', !p.description);
        taskDoneBtn.innerHTML = done
            ? '<i class="bi bi-arrow-counterclockwise me-1"></i> Reabrir'
            : '<i class="bi bi-check2-circle me-1"></i> Concluir';
        // Botão de série só quando a tarefa faz parte de uma recorrência.
        taskDeleteSeriesBtn.classList.toggle('d-none', !event.extendedProps.seriesId);
        taskActionsErr.classList.add('d-none');
        taskActionsModal.show();
    }
    function eventAction(url, body, method) {
        return fetch(url, {
            method: method || 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : null,
        }).then(function (r) {
            if (r.ok) { window.location.reload(); return; }
            throw new Error('Não foi possível concluir a ação.');
        }).catch(function (err) {
            taskActionsErr.textContent = err.message; taskActionsErr.classList.remove('d-none');
        });
    }
    taskDoneBtn.addEventListener('click', function () {
        if (!currentEvent) return;
        eventAction(EVENT_DONE_URL, { event_id: currentEvent.extendedProps.eventId, done: !currentEvent.extendedProps.done });
    });
    taskDeleteBtn.addEventListener('click', function () {
        if (!currentEvent) return;
        if (!confirm('Excluir esta tarefa?')) return;
        eventAction(EVENT_DESTROY_URL + '/' + currentEvent.extendedProps.eventId, null, 'DELETE');
    });
    document.getElementById('taskEditBtn').addEventListener('click', function () {
        if (!currentEvent) return;
        taskActionsModal.hide();
        openTaskEdit(currentEvent);
    });
    taskDeleteSeriesBtn.addEventListener('click', function () {
        if (!currentEvent) return;
        if (!confirm('Excluir TODAS as ocorrências desta tarefa recorrente?')) return;
        eventAction(EVENT_DESTROY_URL + '/' + currentEvent.extendedProps.eventId + '/serie', null, 'DELETE');
    });
})();
</script>

{{-- ===================== KANBAN: arrastar + criar/editar ===================== --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const STORE_URL = "{{ route('kanban.store') }}";
    const MOVE_URL = "{{ route('kanban.move') }}";
    const BASE_URL = "{{ url('kanban') }}"; // + '/' + id
    const $ = (id) => document.getElementById(id);
    const cardModal = new bootstrap.Modal($('cardModal'));

    // ---- Criar / editar cartão ----
    window.openCard = function (el, status, board) {
        const form = $('cardForm');
        $('card_delete').classList.add('d-none');
        $('card_board').value = el ? (el.dataset.board || 'equipe') : (board || 'equipe');
        // Reset etiqueta para "nenhuma"
        form.querySelectorAll('input[name="color"]').forEach((r) => { r.checked = (r.value === ''); });

        if (el) { // editar
            $('card_title_h').textContent = 'Editar cartão';
            form.action = BASE_URL + '/' + el.dataset.id;
            $('card_method').value = 'PUT';
            $('card_status').value = el.closest('.kanban-list').dataset.status;
            $('card_title').value = el.dataset.title || '';
            $('card_desc').value = el.dataset.description || '';
            $('card_assignee').value = el.dataset.assignee || '';
            $('card_due').value = el.dataset.due || '';
            const col = el.dataset.color || '';
            const radio = form.querySelector('input[name="color"][value="' + col + '"]');
            if (radio) radio.checked = true;
            // Botão excluir
            const del = $('card_delete');
            del.classList.remove('d-none');
            del.onclick = function () {
                if (!confirm('Excluir este cartão?')) return;
                const df = $('cardDeleteForm');
                df.action = BASE_URL + '/' + el.dataset.id;
                df.submit();
            };
        } else { // novo
            $('card_title_h').textContent = 'Novo cartão';
            form.action = STORE_URL;
            $('card_method').value = 'POST';
            $('card_status').value = status || 'todo';
            $('card_title').value = ''; $('card_desc').value = '';
            $('card_assignee').value = ''; $('card_due').value = '';
        }
        cardModal.show();
    };

    // Selecionar responsável guarda o nome também (para exibir no cartão).
    const assigneeSel = $('card_assignee');
    const cardForm = $('cardForm');
    cardForm.addEventListener('submit', function () {
        let hidden = cardForm.querySelector('input[name="assignee_name"]');
        if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'assignee_name'; cardForm.appendChild(hidden); }
        hidden.value = assigneeSel.value ? assigneeSel.options[assigneeSel.selectedIndex].text : '';
    });

    // ---- Arrastar entre colunas (persiste coluna + ordem) ----
    document.querySelectorAll('.kanban-list').forEach(function (list) {
        new Sortable(list, {
            group: 'kanban-' + (list.dataset.board || 'equipe'), // não mistura os dois quadros
            animation: 150,
            draggable: '.kanban-card',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onStart: function () { document.querySelectorAll('.tooltip').forEach(function (t) { t.remove(); }); },
            onEnd: function (evt) {
                const target = evt.to;
                const ids = Array.from(target.querySelectorAll('.kanban-card')).map((c) => c.dataset.id);
                fetch(MOVE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ status: target.dataset.status, order: ids }),
                }).catch(function () { alert('Não foi possível salvar a movimentação.'); });
            },
        });
    });
    // Tooltip com o conteúdo do cartão ao passar o mouse.
    document.querySelectorAll('.kanban-card[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, { container: 'body' });
    });
})();
</script>
@endpush
