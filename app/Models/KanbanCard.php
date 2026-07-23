<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cartão do quadro Kanban da equipe (dados locais do portal, sem GLPI). */
class KanbanCard extends Model
{
    public const STATUSES = ['todo', 'doing', 'done'];

    public const BOARDS = ['equipe', 'urgente'];

    protected $fillable = [
        'board',
        'status',
        'title',
        'description',
        'assignee_glpi_id',
        'assignee_name',
        'due_date',
        'color',
        'position',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'assignee_glpi_id' => 'integer',
            'position' => 'integer',
        ];
    }
}
