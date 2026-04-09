<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('stock_audit_vendors')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_staff_name', 100)->nullable();
            $table->string('external_staff_document', 20)->nullable();
            $table->string('role', 30); // contador, conferente, auditor, supervisor
            $table->boolean('is_third_party')->default(false);
            $table->timestamps();
        });

        Schema::create('stock_audit_area_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('stock_audit_areas')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('stock_audit_teams')->cascadeOnDelete();
            $table->unsignedTinyInteger('count_round');
            $table->timestamps();

            $table->unique(['area_id', 'count_round', 'team_id'], 'area_round_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_area_assignments');
        Schema::dropIfExists('stock_audit_teams');
    }
};
