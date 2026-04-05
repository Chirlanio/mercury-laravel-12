<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique(); // id original -> code
            $table->string('name', 60);
            $table->string('cnpj', 14);
            $table->string('company_name', 120); // razao_social
            $table->string('state_registration', 9)->nullable(); // ins_estadual
            $table->string('address', 255);
            $table->unsignedBigInteger('network_id'); // rede_id
            $table->unsignedBigInteger('manager_id'); // func_id (manager employee)
            $table->integer('store_order'); // order_store
            $table->integer('network_order');
            $table->unsignedBigInteger('supervisor_id'); // super_id
            $table->unsignedBigInteger('status_id')->default(1);
            $table->timestamps();

            $table->index(['status_id']);
            $table->index(['network_id']);
            $table->index(['manager_id']);
            $table->index(['supervisor_id']);
            $table->index(['code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
