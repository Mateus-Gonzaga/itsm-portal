<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_internet_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('papel', 20)->default('principal'); // principal|contingencia
            $table->string('provedora')->nullable();
            $table->string('plano')->nullable();
            $table->string('velocidade_download', 40)->nullable();
            $table->string('velocidade_upload', 40)->nullable();
            $table->string('tipo_conexao', 30)->nullable(); // fibra|cabo|radio|satelite|starlink|outro
            $table->boolean('ip_publico')->default(false);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_internet_links');
    }
};
