<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_interactions')) {
            return;
        }

        Schema::create('hd_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('hd_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('comment')->nullable();
            $table->enum('type', ['comment', 'status_change', 'assignment', 'priority_change'])->default('comment');
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_interactions');
    }
};
