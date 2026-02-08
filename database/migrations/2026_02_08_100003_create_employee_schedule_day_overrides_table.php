<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedule_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_work_schedule_id');
            $table->tinyInteger('day_of_week');
            $table->boolean('is_work_day')->default(false);
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['employee_work_schedule_id', 'day_of_week'], 'ews_day_unique');
            $table->foreign('employee_work_schedule_id', 'ews_override_fk')->references('id')->on('employee_work_schedules')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedule_day_overrides');
    }
};
