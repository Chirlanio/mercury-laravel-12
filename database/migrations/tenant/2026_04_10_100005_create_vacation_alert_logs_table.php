<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacation_period_id')->constrained('vacation_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('alert_type', 30); // 90_days, 60_days, 30_days, expired
            $table->text('message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vacation_period_id', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_alert_logs');
    }
};
