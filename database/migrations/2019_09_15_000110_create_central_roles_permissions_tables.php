<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('label', 100);
            $table->integer('hierarchy_level')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('hierarchy_level');
        });

        Schema::create('central_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('label', 200);
            $table->text('description')->nullable();
            $table->string('group', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('group');
        });

        Schema::create('central_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('central_role_id')->constrained('central_roles')->cascadeOnDelete();
            $table->foreignId('central_permission_id')->constrained('central_permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['central_role_id', 'central_permission_id'], 'crp_role_perm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_role_permissions');
        Schema::dropIfExists('central_permissions');
        Schema::dropIfExists('central_roles');
    }
};
