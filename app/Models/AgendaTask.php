<?php

namespace App\Models;

use App\Services\GoogleCalendarService;
use Illuminate\Database\Eloquent\Model;

/** Tarefa livre da equipe na agenda (dados locais do portal, com recorrência). */
class AgendaTask extends Model
{
    protected $fillable = [
        'series_id',
        'google_event_id',
        'title',
        'description',
        'color',
        'owner_glpi_id',
        'owner_name',
        'start_at',
        'end_at',
        'done',
        'created_by',
    ];

    /**
     * Espelha a tarefa no Google Calendar (quando a sync está ligada).
     * Só dispara em ações de tela (web); em console (seeders, comando de pull)
     * não empurra — evita floods e laço de eco.
     */
    protected static function booted(): void
    {
        $push = fn (string $op) => function (AgendaTask $task) use ($op) {
            if (app()->runningInConsole()) {
                return;
            }
            $svc = app(GoogleCalendarService::class);
            match ($op) {
                'create' => $svc->pushCreate($task),
                'update' => $svc->pushUpdate($task),
                'delete' => $svc->pushDelete($task),
            };
        };

        static::created($push('create'));
        static::updated($push('update'));
        static::deleted($push('delete'));
    }

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'done' => 'boolean',
            'owner_glpi_id' => 'integer',
        ];
    }
}
