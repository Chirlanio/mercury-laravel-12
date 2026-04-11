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
            if (! Schema::hasColumn('deliveries', 'invoice_number')) {
                $table->string('invoice_number', 50)->after('client_name');
            }
            if (! Schema::hasColumn('deliveries', 'products_qty')) {
                $table->unsignedSmallInteger('products_qty')->default(1)->after('installments');
            }
            if (! Schema::hasColumn('deliveries', 'exit_point')) {
                $table->string('exit_point', 50)->nullable()->after('products_qty');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'products_qty', 'exit_point']);
        });
    }
};
