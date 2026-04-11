<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_number', 20)->unique();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('date_route');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'date_route']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_routes');
    }
};
