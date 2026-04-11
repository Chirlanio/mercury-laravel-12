<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('drivers') && ! Schema::hasColumn('drivers', 'user_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('drivers') && Schema::hasColumn('drivers', 'user_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
