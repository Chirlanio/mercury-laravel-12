<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alterar a coluna type para incluir 'compensar'
        DB::statement("ALTER TABLE work_shifts MODIFY COLUMN type ENUM('abertura', 'fechamento', 'integral', 'compensar') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para os valores originais
        DB::statement("ALTER TABLE work_shifts MODIFY COLUMN type ENUM('abertura', 'fechamento', 'integral') NOT NULL");
    }
};
