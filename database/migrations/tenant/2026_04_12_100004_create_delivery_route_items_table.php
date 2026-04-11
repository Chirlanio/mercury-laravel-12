<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_route_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence_order')->default(0);

            // Cached data (for offline/print)
            $table->string('client_name')->nullable();
            $table->text('address')->nullable();

            // Completion
            $table->timestamp('delivered_at')->nullable();
            $table->string('received_by')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['route_id', 'delivery_id']);
            $table->index('route_id');
            $table->index('delivery_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_items');
    }
};
