<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('address');
            }
            if (! Schema::hasColumn('deliveries', 'longitude')) {
                $table->decimal('longitude', 10, 8)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('deliveries', 'geocoded_at')) {
                $table->timestamp('geocoded_at')->nullable()->after('longitude');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geocoded_at']);
        });
    }
};
