<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_cards', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('todo'); // todo | doing | done
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('assignee_glpi_id')->nullable();
            $table->string('assignee_name')->nullable();
            $table->date('due_date')->nullable();
            $table->string('color', 20)->nullable();
            $table->integer('position')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_cards');
    }
};
