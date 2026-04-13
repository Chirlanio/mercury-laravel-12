<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'phone_primary')) {
                // Brazilian mobile with country code = up to 13 chars (55 + 2 + 9 + 8).
                // Stored un-normalized so we preserve whatever the admin typed;
                // matching happens against a normalized version at query time.
                $table->string('phone_primary', 20)->nullable()->after('cpf');
                $table->index('phone_primary');
            }
            if (! Schema::hasColumn('employees', 'phone_last_used_at')) {
                // Stamps the last time the driver saw this phone matching —
                // helps detect stale/shared numbers during audit.
                $table->timestamp('phone_last_used_at')->nullable()->after('phone_primary');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'phone_last_used_at')) {
                $table->dropColumn('phone_last_used_at');
            }
            if (Schema::hasColumn('employees', 'phone_primary')) {
                $table->dropIndex(['phone_primary']);
                $table->dropColumn('phone_primary');
            }
        });
    }
};
