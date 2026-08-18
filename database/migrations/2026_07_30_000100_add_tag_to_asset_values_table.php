<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_values', function (Blueprint $table) {
            $table->string('tag', 60)->nullable()->after('item_id'); // etiqueta/patrimônio
            // agora um ativo pode ter só etiqueta (sem valor definido)
            $table->decimal('value', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_values', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }
};
