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
    .fc-list-event.ev-done .fc-list-event-dot { border-color:#9aa1a8; }
    .fc-list-event.ev-done .fc-list-event-title { text-decoration:line-through; opacity:.8; }
    .fc .fc-list-event-title .bi { color:var(--bs-secondary-color); margin-right:.3rem; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <h1 class="h4 mb-0">Agenda</h1>
    <p class="text-secondary small mb-0">Tarefas agendadas dos chamados e prazos de atendimento.</p>
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
            <span><span class="dot dot-task"></span>Tarefa</span>
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
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
(function () {
    const ALL_EVENTS = @json($events);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const RESCHEDULE_URL = "{{ route('agenda.reschedule') }}";
    const STORE_URL = "{{ route('agenda.store') }}";
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
        if (p.type !== 'task' || !p.taskId) { info.revert(); return; }
        fetch(RESCHEDULE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({
                task_id: p.taskId,
                begin: info.event.start.toISOString(),
                end: (info.event.end || new Date(info.event.start.getTime() + 3600000)).toISOString(),
            }),
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
            novo: { text: 'Novo agendamento', click: function () { openModal(new Date(), true); } },
        },
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'novo dayGridMonth,timeGridWeek,listMonth' },
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
            const id = info.event.extendedProps.ticketId;
            if (id) window.location = TICKET_URL + '/' + id;
        },
        dateClick: function (info) { openModal(info.date, info.allDay); },
        eventContent: function (arg) {
            const p = arg.event.extendedProps;
            const icon = p.type === 'sla' ? 'bi-clock-history' : (p.done ? 'bi-check2-circle' : 'bi-tools');
            // Visão Lista: usa o ícone no título e deixa o resto no render nativo.
            if (arg.view.type.indexOf('list') === 0) {
                return { html: '<i class="bi ' + icon + '"></i>' + arg.event.title };
            }
            const wrap = document.createElement('div');
            wrap.className = 'ev-inner';
            let html = '<i class="bi ' + icon + '"></i>';
            if (arg.timeText) html += '<span class="ev-time">' + arg.timeText + '</span>';
            html += '<span class="ev-title">' + arg.event.title + '</span>';
            if (p.type === 'task' && p.technicianName) html += '<span class="ev-tech">' + p.technicianName + '</span>';
            wrap.innerHTML = html;
            return { domNodes: [wrap] };
        },
        events: visibleEvents(),
    });
    calendar.render();

    const novoBtn = el.querySelector('.fc-novo-button');
    if (novoBtn) { novoBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Novo agendamento'; novoBtn.title = 'Agendar um atendimento'; }

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
})();
</script>
@endpush
