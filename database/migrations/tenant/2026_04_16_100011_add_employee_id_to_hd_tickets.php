<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_tickets') || Schema::hasColumn('hd_tickets', 'employee_id')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            // employee_id is "about whom" the ticket is. Distinct from requester_id,
            // which is "who opened it". For WhatsApp intake the requester is the
            // system bot; the real human is tracked here (when CPF resolves).
            $table->foreignId('employee_id')
                ->nullable()
                ->after('requester_id')
                ->constrained('employees')
                ->nullOnDelete();
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_tickets') || ! Schema::hasColumn('hd_tickets', 'employee_id')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
