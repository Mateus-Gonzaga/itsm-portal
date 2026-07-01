<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardsController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TechniciansController;
use App\Http\Controllers\ServiceWindowController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Notificações do sino (derivadas do GLPI) — poll via AJAX.
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificacoes/ler', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Módulos futuros (placeholders — estrutura preparada / "em construção")
    Route::get('/dashboard-analitico', DashboardsController::class)->middleware('role:gestor')->name('modules.analytics');
    Route::get('/relatorios', ReportsController::class)->middleware('role:gestor')->name('modules.reports');
    Route::get('/clientes', ClientsController::class)->middleware('role:gestor')->name('modules.clients');

    // Escrita do diretório (entidades/usuários) — gestor (+ rate limit anti-abuso)
    Route::middleware(['role:gestor', 'throttle:30,1'])->group(function () {
        Route::post('/diretorio/entidades', [DirectoryController::class, 'storeEntity'])->name('directory.entities.store');
        Route::put('/diretorio/entidades/{id}', [DirectoryController::class, 'updateEntity'])->name('directory.entities.update');
        Route::post('/diretorio/usuarios', [DirectoryController::class, 'storeUser'])->name('directory.users.store');
        Route::put('/diretorio/usuarios/{id}', [DirectoryController::class, 'updateUser'])->name('directory.users.update');
        Route::put('/diretorio/usuarios/{id}/ativo', [DirectoryController::class, 'toggleUser'])->name('directory.users.toggle');
    });
    Route::get('/tecnicos', TechniciansController::class)->middleware('role:gestor')->name('modules.technicians');
    Route::get('/configuracoes', fn () => view('modules.settings'))->name('modules.settings');

    // Janela de atendimento (horários/SLA) — gestor
    Route::middleware('role:gestor')->group(function () {
        Route::get('/janela-atendimento', [ServiceWindowController::class, 'index'])->name('modules.schedule');
        Route::post('/janela-atendimento', [ServiceWindowController::class, 'update'])->name('modules.schedule.update');
    });
    Route::get('/inventario', [InventoryController::class, 'index'])->name('modules.inventory');
    Route::post('/inventario/mover', [InventoryController::class, 'move'])
        ->middleware(['role:gestor', 'throttle:30,1'])->name('inventory.move');
    Route::get('/base-conhecimento', fn () => view('modules.placeholder', ['title' => 'Base de Conhecimento', 'icon' => 'bi-journal-text', 'desc' => 'Artigos e soluções para autoatendimento.']))->name('modules.kb');
    Route::get('/monitoramento', fn () => view('modules.placeholder', ['title' => 'Monitoramento', 'icon' => 'bi-activity', 'desc' => 'Status de serviços e alertas em tempo real.']))->name('modules.monitoring');
    Route::get('/automacoes', fn () => view('modules.placeholder', ['title' => 'Automações', 'icon' => 'bi-robot', 'desc' => 'Regras e fluxos automáticos de atendimento.']))->name('modules.automations');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/meus-chamados', [TicketController::class, 'mine'])->name('tickets.mine');

    // Cliente e técnico podem abrir chamados.
    Route::middleware('role:cliente,tecnico')->group(function () {
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    });

    Route::get('/tickets/{id}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{id}/comments', [TicketController::class, 'addComment'])->name('tickets.comments.store');

    // Ações do atendimento (técnico/gestor)
    Route::middleware('role:tecnico,gestor')->group(function () {
        Route::post('/tickets/{id}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::post('/tickets/{id}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
        Route::post('/tickets/{id}/sla', [TicketController::class, 'updateSla'])->name('tickets.sla');

        // Agenda interna (calendário de tarefas + prazos)
        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('/agenda/remarcar', [AgendaController::class, 'reschedule'])->name('agenda.reschedule');
        Route::post('/agenda/agendar', [AgendaController::class, 'store'])->name('agenda.store');

        // Tarefas livres da equipe (PlanningExternalEvent)
        Route::post('/agenda/tarefa', [AgendaController::class, 'storeEvent'])->name('agenda.event.store');
        Route::post('/agenda/tarefa/remarcar', [AgendaController::class, 'rescheduleEvent'])->name('agenda.event.reschedule');
        Route::post('/agenda/tarefa/concluir', [AgendaController::class, 'toggleEventDone'])->name('agenda.event.done');
        Route::delete('/agenda/tarefa/{id}', [AgendaController::class, 'destroyEvent'])->name('agenda.event.destroy');
    });

    // Ações do solicitante (cliente)
    Route::middleware('role:cliente')->group(function () {
        Route::post('/tickets/{id}/approve', [TicketController::class, 'approve'])->name('tickets.approve');
        Route::post('/tickets/{id}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
    });
});
