<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_departments')) {
            return;
        }

        if (! Schema::hasColumn('hd_departments', 'requires_identification')) {
            Schema::table('hd_departments', function (Blueprint $table) {
                $table->boolean('requires_identification')
                    ->default(false)
                    ->after('auto_assign');
            });
        }

        // Opinionated default: DP always needs identification because every
        // request (vacation, payslip, certificate) ties to an employee record.
        // Others stay false until the tenant admin enables them.
        DB::table('hd_departments')
            ->where('name', 'DP')
            ->update(['requires_identification' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_departments') || ! Schema::hasColumn('hd_departments', 'requires_identification')) {
            return;
        }

        Schema::table('hd_departments', function (Blueprint $table) {
            $table->dropColumn('requires_identification');
        });
    }
};
