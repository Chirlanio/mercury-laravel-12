<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna legacy_id permite rastrear remanejos importados do sistema antigo
 * (adms_relocations / adms_relocation_items do u401878354_meiaso26_bd_me)
 * pra simulação e backup histórico. Indexed unique pra que o comando de
 * import seja idempotente — re-rodar não duplica registros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relocations', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->after('ulid');
            $table->unique('legacy_id', 'relocations_legacy_id_unique');
        });

        Schema::table('relocation_items', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->after('id');
            $table->unique('legacy_id', 'relocation_items_legacy_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('relocation_items', function (Blueprint $table) {
            $table->dropUnique('relocation_items_legacy_id_unique');
            $table->dropColumn('legacy_id');
        });

        Schema::table('relocations', function (Blueprint $table) {
            $table->dropUnique('relocations_legacy_id_unique');
            $table->dropColumn('legacy_id');
        });
    }
};
