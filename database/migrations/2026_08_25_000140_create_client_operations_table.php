<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Informações operacionais e de atendimento do cliente (1×1). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_profile_id')->unique()->constrained('client_profiles')->cascadeOnDelete();
            // Operacional
            $table->string('horario_funcionamento')->nullable();
            $table->string('dias_funcionamento')->nullable();
            $table->boolean('atende_fora_horario')->default(false);
            $table->string('restricoes_horario')->nullable();
            $table->text('instrucoes_acesso')->nullable();
            $table->text('procedimentos_especiais')->nullable();
            $table->text('observacoes_operacionais')->nullable();
            // Atendimento (notas internas para técnicos)
            $table->text('info_tecnicos')->nullable();
            $table->text('observacoes_internas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_operations');
    }
};
