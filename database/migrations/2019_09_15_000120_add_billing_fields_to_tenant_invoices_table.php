<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->string('billing_cycle', 10)->default('monthly')->after('currency');
            $table->string('gateway_provider', 50)->nullable()->after('transaction_id');
            $table->string('gateway_id')->nullable()->after('gateway_provider');
            $table->string('payment_url')->nullable()->after('gateway_id');
            $table->boolean('auto_generated')->default(false)->after('payment_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'gateway_provider', 'gateway_id', 'payment_url', 'auto_generated']);
        });
    }
};
