<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_task_google_events', function (Blueprint ) {
            ->unsignedBigInteger('ticket_task_id')->primary();
            ->unsignedBigInteger('ticket_id')->nullable()->index();
            ->string('google_event_id', 1024)->index();
            ->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_task_google_events');
    }
};
