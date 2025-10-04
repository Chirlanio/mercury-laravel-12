<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('jornada_controle', 'work_shifts');

        Schema::table('work_shifts', function (Blueprint $table) {
            $table->renameColumn('data', 'date');
            $table->renameColumn('hora_inicio', 'start_time');
            $table->renameColumn('hora_termino', 'end_time');
            $table->renameColumn('tipo', 'type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->renameColumn('date', 'data');
            $table->renameColumn('start_time', 'hora_inicio');
            $table->renameColumn('end_time', 'hora_termino');
            $table->renameColumn('type', 'tipo');
        });

        Schema::rename('work_shifts', 'jornada_controle');
    }
};
