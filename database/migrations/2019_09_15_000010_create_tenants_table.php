<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('cnpj', 18)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained('tenant_plans')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('owner_name');
            $table->string('owner_email');
            $table->json('settings')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
