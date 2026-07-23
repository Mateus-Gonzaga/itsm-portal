<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tarefa livre da equipe na agenda (dados locais do portal, com recorrência). */
class AgendaTask extends Model
{
    protected $fillable = [
        'series_id',
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
