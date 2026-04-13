<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 30)->nullable()->after('email');
                $table->index('phone');
            });
        }

        // Drop and recreate the requester_id FK as nullable for hd_tickets.
        // External contacts (WhatsApp, email) may arrive before we have a
        // matching User row — the ticket still exists and the contact info
        // is tracked via hd_ticket_channels.
        if (Schema::hasTable('hd_tickets') && Schema::hasColumn('hd_tickets', 'requester_id')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite cannot ALTER columns cleanly — the nullable change is
                // safe to skip because the test environment doesn't exercise
                // external-contact tickets (those are tested via factories
                // that always set requester_id).
                return;
            }

            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->dropForeign(['requester_id']);
            });

            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->foreignId('requester_id')->nullable()->change();
            });

            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->foreign('requester_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['phone']);
                $table->dropColumn('phone');
            });
        }

        // Intentionally not reverting the requester_id nullability — once
        // nullable tickets exist in production, forcing NOT NULL back would
        // require data migration. Leave as nullable; re-apply NOT NULL manually
        // if ever needed.
    }
};
