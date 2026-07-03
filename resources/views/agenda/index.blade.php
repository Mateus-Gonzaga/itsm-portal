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
</style>
@endpush

@section('content')
<div class="mb-3">
    <h1 class="h4 mb-0">Agenda</h1>
    <p class="text-secondary small mb-0">Tarefas dos chamados, prazos de atendimento e tarefas livres da equipe. Clique num dia para lançar uma tarefa.</p>
</div>

<div class="card agenda-card">
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
                    <h5 class="modal-title"><i class="bi bi-list-task me-2"></i>Nova tarefa</h5>
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
                    <div class="mb-1">
                        <label class="form-label">Detalhes <span class="text-muted small">— opcional</span></label>
                        <textarea name="content" rows="2" class="form-control" placeholder="Observações da tarefa."></textarea>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="taskError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1"></i> Criar tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: ações de uma tarefa livre --}}
<div class="modal fade" id="taskActionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-list-task me-2"></i>Tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="taskActionsTitle"></p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success" id="taskDoneBtn"><i class="bi bi-check2-circle me-1"></i> Concluir</button>
                    <button type="button" class="btn btn-outline-danger" id="taskDeleteBtn"><i class="bi bi-trash me-1"></i> Excluir</button>
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
    const TICKET_URL = "{{ url('tickets') }}";

    let filterTech = '';
    let showSla = true;

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
        eventClick: function (info) {
            const p = info.event.extendedProps;
            if (p.type === 'event') { openTaskActions(info.event); return; }
            if (p.ticketId) window.location = TICKET_URL + '/' + p.ticketId;
        },
        dateClick: function (info) { openTaskModal(info.date, info.allDay); },
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

    function openTaskModal(date, allDay) {
        const begin = new Date(date);
        if (allDay || (begin.getHours() === 0 && begin.getMinutes() === 0)) begin.setHours(9, 0, 0, 0);
        const end = new Date(begin.getTime() + 3600000);
        taskForm.reset();
        taskForm.begin.value = fmt(begin);
        taskForm.end.value = fmt(end);
        taskErr.classList.add('d-none');
        taskModal.show();
    }
    taskForm.addEventListener('submit', function (e) {
        e.preventDefault();
        taskErr.classList.add('d-none');
        const payload = {
            title: taskForm.title.value,
            owner_glpi_id: taskForm.owner_glpi_id.value || null,
            begin: new Date(taskForm.begin.value).toISOString(),
            end: new Date(taskForm.end.value).toISOString(),
            content: taskForm.content.value,
        };
        fetch(EVENT_STORE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (r) {
            if (r.ok) { window.location.reload(); return; }
            return r.json().then(function (j) { throw new Error(j.message || 'Não foi possível criar a tarefa.'); });
        }).catch(function (err) {
            taskErr.textContent = err.message; taskErr.classList.remove('d-none');
        });
    });

    // ---- Modal de ações da tarefa livre (concluir / excluir) ----
    const taskActionsModal = new bootstrap.Modal(document.getElementById('taskActionsModal'));
    const taskActionsErr = document.getElementById('taskActionsError');
    const taskDoneBtn = document.getElementById('taskDoneBtn');
    const taskDeleteBtn = document.getElementById('taskDeleteBtn');
    let currentEvent = null;

    function openTaskActions(event) {
        currentEvent = event;
        const done = event.extendedProps.done;
        document.getElementById('taskActionsTitle').textContent = event.title;
        taskDoneBtn.innerHTML = done
            ? '<i class="bi bi-arrow-counterclockwise me-1"></i> Reabrir'
            : '<i class="bi bi-check2-circle me-1"></i> Concluir';
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
})();
</script>
@endpush
