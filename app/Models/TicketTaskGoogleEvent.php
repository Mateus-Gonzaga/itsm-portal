<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketTaskGoogleEvent extends Model
{
    protected $primaryKey = 'ticket_task_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'ticket_task_id',
        'ticket_id',
        'google_event_id',
    ];
}
