<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('series_id', 40)->nullable()->index(); // agrupa ocorrências recorrentes
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('owner_glpi_id')->nullable();
            $table->string('owner_name')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('done')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('start_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_tasks');
    }
};
