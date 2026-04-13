<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_departments')) {
            return;
        }

        if (Schema::hasColumn('hd_departments', 'auto_assign')) {
            return;
        }

        Schema::table('hd_departments', function (Blueprint $table) {
            $table->boolean('auto_assign')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('hd_departments', 'auto_assign')) {
            Schema::table('hd_departments', function (Blueprint $table) {
                $table->dropColumn('auto_assign');
            });
        }
    }
};
