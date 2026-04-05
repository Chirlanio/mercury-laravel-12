<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('provider'); // cigam, sap, totvs, custom
            $table->string('type'); // erp, crm, ecommerce, custom
            $table->string('driver'); // database, rest_api, webhook
            $table->text('config')->nullable(); // encrypted JSON
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status')->nullable(); // success, error, running
            $table->text('last_sync_message')->nullable();
            $table->string('sync_schedule')->nullable(); // cron expression
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_integrations');
    }
};
