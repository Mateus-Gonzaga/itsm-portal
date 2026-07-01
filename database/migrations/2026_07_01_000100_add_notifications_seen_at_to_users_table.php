<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Momento em que o usuário "leu" o sino. Notificações = atividade do
            // GLPI depois deste carimbo. Null = nunca leu (janela padrão recente).
            $table->timestamp('notifications_seen_at')->nullable()->after('glpi_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifications_seen_at');
        });
    }
};
