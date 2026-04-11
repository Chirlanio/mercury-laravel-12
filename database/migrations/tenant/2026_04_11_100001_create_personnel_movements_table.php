<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // dismissal, promotion, transfer, reactivation
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('store_id', 10); // Store code (same pattern as employees.store_id)
            $table->string('status', 30)->default('pending');
            $table->date('effective_date')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('request_area_id')->nullable()->constrained('sectors')->nullOnDelete();

            // === DISMISSAL FIELDS ===
            $table->string('contact', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('contract_type', 30)->nullable(); // clt, trial, intern, apprentice
            $table->string('dismissal_subtype', 40)->nullable(); // company_initiative, employee_resignation, trial_end, just_cause
            $table->string('early_warning', 30)->nullable(); // worked, indemnified, dispensed
            $table->date('last_day_worked')->nullable();

            // Access control (14 fields)
            $table->boolean('access_power_bi')->default(false);
            $table->boolean('access_zznet')->default(false);
            $table->boolean('access_cigam')->default(false);
            $table->boolean('access_camera')->default(false);
            $table->boolean('access_deskfy')->default(false);
            $table->boolean('access_meu_atendimento')->default(false);
            $table->boolean('access_dito')->default(false);
            $table->boolean('access_notebook')->default(false);
            $table->boolean('access_email_corporate')->default(false);
            $table->boolean('access_parking_card')->default(false);
            $table->boolean('access_parking_shopping')->default(false);
            $table->boolean('access_key_office')->default(false);
            $table->boolean('access_key_store')->default(false);
            $table->boolean('access_instagram')->default(false);

            // Activation fields (4 fields)
            $table->boolean('activate_it')->default(false);
            $table->boolean('activate_operation')->default(false);
            $table->boolean('deactivate_instagram')->default(false);
            $table->boolean('activate_hr')->default(false);

            // Integration data
            $table->unsignedInteger('fouls')->default(0);
            $table->unsignedInteger('days_off')->default(0);
            $table->string('overtime_hours', 10)->nullable(); // HH:MM format
            $table->decimal('fixed_fund', 10, 2)->nullable();
            $table->boolean('open_vacancy')->default(false);

            // === PROMOTION FIELDS ===
            $table->foreignId('new_position_id')->nullable()->constrained('positions')->nullOnDelete();

            // === TRANSFER FIELDS ===
            $table->string('origin_store_id', 10)->nullable();
            $table->string('destination_store_id', 10)->nullable();

            // === REACTIVATION FIELDS ===
            $table->date('reactivation_date')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Soft delete
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();

            // Indexes
            $table->index(['employee_id', 'status']);
            $table->index(['store_id', 'type', 'status']);
            $table->index(['type', 'status']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_movements');
    }
};
