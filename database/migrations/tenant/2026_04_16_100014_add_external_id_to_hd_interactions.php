<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_interactions') || Schema::hasColumn('hd_interactions', 'external_id')) {
            return;
        }

        Schema::table('hd_interactions', function (Blueprint $table) {
            // Evolution message_id (or email message-id, etc.) for interactions
            // that were sent to / received from an external channel. Used both
            // for audit and to dedupe webhook events that arrive more than once.
            $table->string('external_id', 191)->nullable()->after('new_value');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_interactions') || ! Schema::hasColumn('hd_interactions', 'external_id')) {
            return;
        }

        Schema::table('hd_interactions', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
