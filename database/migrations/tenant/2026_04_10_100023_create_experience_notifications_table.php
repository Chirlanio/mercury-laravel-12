<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('experience_evaluations')->cascadeOnDelete();
            $table->string('notification_type', 20); // created, reminder_5d, reminder_due, overdue
            $table->string('recipient_type', 10); // employee, manager
            $table->dateTime('sent_at')->useCurrent();

            $table->unique(['evaluation_id', 'notification_type', 'recipient_type'], 'eval_notif_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_notifications');
    }
};
