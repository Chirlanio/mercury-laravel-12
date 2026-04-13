<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        if (Schema::hasColumn('messages', 'edited_at')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('reply_to_message_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages') || ! Schema::hasColumn('messages', 'edited_at')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
