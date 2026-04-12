<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::create('hd_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->constrained('hd_departments');
            $table->foreignId('category_id')->nullable()->constrained('hd_categories')->nullOnDelete();
            $table->string('store_id', 10)->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('status', 20)->default('open');
            $table->unsignedTinyInteger('priority')->default(2);
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['department_id', 'status']);
            $table->index('requester_id');
            $table->index('assigned_technician_id');
            $table->index('sla_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_tickets');
    }
};
