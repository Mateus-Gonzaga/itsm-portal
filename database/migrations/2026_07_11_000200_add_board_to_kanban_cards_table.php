<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_cards', function (Blueprint $table) {
            // Qual quadro: 'equipe' (padrão) ou 'urgente' (Atenção/Urgente).
            $table->string('board', 20)->default('equipe')->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->dropColumn('board');
        });
    }
};
