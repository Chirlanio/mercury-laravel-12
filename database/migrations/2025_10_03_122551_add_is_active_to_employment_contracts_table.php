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
        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('store_id');
        });

        // Definir o contrato mais recente de cada funcionário como ativo
        DB::statement('
            UPDATE employment_contracts ec1
            INNER JOIN (
                SELECT employee_id, MAX(start_date) as max_date
                FROM employment_contracts
                GROUP BY employee_id
            ) ec2 ON ec1.employee_id = ec2.employee_id AND ec1.start_date = ec2.max_date
            SET ec1.is_active = 1
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
