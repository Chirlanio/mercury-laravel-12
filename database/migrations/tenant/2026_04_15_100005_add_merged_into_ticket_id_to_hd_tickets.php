<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        if (Schema::hasColumn('hd_tickets', 'merged_into_ticket_id')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            $table->foreignId('merged_into_ticket_id')
                ->nullable()
                ->after('closed_at')
                ->constrained('hd_tickets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('hd_tickets', 'merged_into_ticket_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('merged_into_ticket_id');
            });
        }
    }
};
