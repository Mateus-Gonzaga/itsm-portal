<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->string('google_event_id', 1024)->nullable()->after('series_id'); // vínculo com o evento no Google Calendar
        });
    }

    public function down(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};
