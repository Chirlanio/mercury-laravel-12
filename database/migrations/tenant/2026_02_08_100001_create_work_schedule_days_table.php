<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_schedule_id');
            $table->tinyInteger('day_of_week'); // 0=Domingo, 6=Sábado
            $table->boolean('is_work_day')->default(false);
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->integer('break_duration_minutes')->nullable();
            $table->decimal('daily_hours', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['work_schedule_id', 'day_of_week']);
            $table->foreign('work_schedule_id')->references('id')->on('work_schedules')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule_days');
    }
};
