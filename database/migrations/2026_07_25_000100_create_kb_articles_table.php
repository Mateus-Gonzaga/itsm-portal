<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('cliente')->nullable()->index(); // cliente/filial
            $table->string('categoria', 40)->default('Geral');
            $table->string('titulo');
            $table->decimal('valor', 12, 2)->nullable(); // estimativa de valor dos ativos
            $table->text('conteudo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
