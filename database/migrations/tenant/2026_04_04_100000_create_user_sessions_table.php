<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_in_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('logged_out_at')->nullable();
            $table->boolean('is_online')->default(true);
            $table->string('current_page')->nullable();
            $table->enum('idle_status', ['active', 'idle'])->default('active');
            $table->timestamp('idle_since')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_online']);
            $table->index(['is_online', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
