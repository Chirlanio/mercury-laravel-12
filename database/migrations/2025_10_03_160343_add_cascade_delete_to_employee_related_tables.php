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
        $driver = DB::connection()->getDriverName();

        // Primeiro, limpar registros órfãos (contratos sem funcionário correspondente)
        DB::statement('
            DELETE FROM employment_contracts
            WHERE employee_id NOT IN (SELECT id FROM employees)
        ');

        if ($driver === 'sqlite') {
            // SQLite: tentar remover o índice se existir (ignora erro se não existir)
            try {
                Schema::table('employment_contracts', function (Blueprint $table) {
                    $table->dropIndex(['employee_id']);
                });
            } catch (\Exception $e) {
                // Index doesn't exist, continue
            }
        } else {
            // MySQL: Verificar e remover índice se existir
            $indexExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = 'employment_contracts'
                AND index_name = 'employment_contracts_employee_id_index'
            ");

            if ($indexExists[0]->count > 0) {
                Schema::table('employment_contracts', function (Blueprint $table) {
                    $table->dropIndex(['employee_id']);
                });
            }
        }

        // Adicionar foreign key com cascade delete para employment_contracts
        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table) {
            // Remover a foreign key e recriar o índice
            $table->dropForeign(['employee_id']);
            $table->index('employee_id');
        });
    }
};
