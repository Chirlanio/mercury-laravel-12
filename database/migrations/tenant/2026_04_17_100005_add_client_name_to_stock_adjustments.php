<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_adjustments', 'client_name')) {
                $table->string('client_name', 150)->nullable()->after('observation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('stock_adjustments', 'client_name')) {
                $table->dropColumn('client_name');
            }
        });
    }
};
