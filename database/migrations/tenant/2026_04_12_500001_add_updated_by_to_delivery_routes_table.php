<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_routes')) {
            return;
        }

        Schema::table('delivery_routes', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_routes', 'updated_by_user_id')) {
                $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_routes')) {
            return;
        }

        Schema::table('delivery_routes', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_routes', 'updated_by_user_id')) {
                $table->dropForeign(['updated_by_user_id']);
                $table->dropColumn('updated_by_user_id');
            }
        });
    }
};
