<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketTaskGoogleEvent extends Model
{
    protected  = 'ticket_task_id';
    public  = false;
    protected  = 'int';

    protected  = [
        'ticket_task_id',
        'ticket_id',
        'google_event_id',
    ];
}
