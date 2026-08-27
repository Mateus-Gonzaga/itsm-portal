<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_values', function (Blueprint $table) {
            $table->string('modelo', 120)->nullable()->after('tag'); // modelo informado no portal
        });
    }

    public function down(): void
    {
        Schema::table('asset_values', function (Blueprint $table) {
            $table->dropColumn('modelo');
        });
    }
};
