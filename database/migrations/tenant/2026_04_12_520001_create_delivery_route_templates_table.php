<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_route_templates')) {
            return;
        }

        Schema::create('delivery_route_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->decimal('start_point_lat', 10, 8)->nullable();
            $table->decimal('start_point_lng', 10, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_templates');
    }
};
