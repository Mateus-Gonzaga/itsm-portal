<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Infraestrutura de rede do cliente (1×1). Não substitui o inventário do GLPI. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_infrastructure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_profile_id')->unique()->constrained('client_profiles')->cascadeOnDelete();
            $table->boolean('possui_firewall')->default(false);
            $table->string('firewall_modelo')->nullable();
            $table->boolean('possui_roteador')->default(false);
            $table->string('roteador_modelo')->nullable();
            $table->string('switch_principal')->nullable();
            $table->unsignedSmallInteger('quantidade_switches')->nullable();
            $table->boolean('wifi_corporativo')->default(false);
            $table->unsignedSmallInteger('quantidade_access_points')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_infrastructure');
    }
};
