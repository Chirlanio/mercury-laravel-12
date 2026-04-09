<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->unsignedTinyInteger('count_round');
            $table->foreignId('area_id')->nullable()->constrained('stock_audit_areas')->nullOnDelete();
            $table->string('file_name', 255);
            $table->string('format_type', 20); // collector, tabular
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->string('rejected_csv_path', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_import_logs');
    }
};
