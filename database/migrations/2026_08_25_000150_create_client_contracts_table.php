<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('numero')->nullable();
            $table->string('tipo')->nullable(); // suporte|monitoramento|infra|redes|cftv|...
            $table->date('data_inicio')->nullable();
            $table->date('data_termino')->nullable();
            $table->string('status', 20)->default('ativo'); // ativo|suspenso|renovacao|encerrado
            $table->decimal('valor_mensal', 12, 2)->nullable();
            $table->string('sla')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contracts');
    }
};
