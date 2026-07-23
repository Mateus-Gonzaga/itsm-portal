<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            // Cor da tarefa no calendário (hex). Null = cor padrão (índigo).
            $table->string('color', 20)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
