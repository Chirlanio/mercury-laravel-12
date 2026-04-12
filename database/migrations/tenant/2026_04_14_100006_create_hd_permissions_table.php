<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_permissions')) {
            return;
        }

        Schema::create('hd_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('hd_departments')->cascadeOnDelete();
            $table->enum('level', ['technician', 'manager'])->default('technician');
            $table->primary(['user_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_permissions');
    }
};
