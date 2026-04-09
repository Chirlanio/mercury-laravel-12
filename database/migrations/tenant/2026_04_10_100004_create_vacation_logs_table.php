<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacation_id')->constrained('vacations')->cascadeOnDelete();
            $table->string('action_type', 30); // CREATED, SUBMITTED, MANAGER_APPROVED, HR_APPROVED, STARTED, FINISHED, CANCELLED, MANAGER_REJECTED, HR_REJECTED, RETROACTIVE_CREATED
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->foreignId('changed_by_user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vacation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_logs');
    }
};
