<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_route_template_stops')) {
            return;
        }

        Schema::create('delivery_route_template_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('delivery_route_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence_order')->default(0);
            $table->string('neighborhood')->nullable();
            $table->string('address')->nullable();
            $table->string('reference_name')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            $table->timestamps();

            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_template_stops');
    }
};
