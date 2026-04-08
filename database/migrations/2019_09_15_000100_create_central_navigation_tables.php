<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Central page groups (Listar, Cadastrar, Editar, etc.)
        Schema::create('central_page_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        // Central menus (navigation structure)
        Schema::create('central_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 220);
            $table->string('icon', 40)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('type', ['main', 'hr', 'utility', 'system'])->default('main');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('central_menus')->onDelete('cascade');
            $table->index(['order', 'is_active']);
        });

        // Central pages (all platform pages)
        Schema::create('central_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_name', 220);
            $table->string('route', 100)->nullable();
            $table->string('controller', 220)->nullable();
            $table->string('method', 220)->nullable();
            $table->string('menu_controller', 220)->nullable();
            $table->string('menu_method', 220)->nullable();
            $table->string('icon', 40)->nullable();
            $table->mediumText('notes')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('central_page_group_id')->nullable()->constrained('central_page_groups')->nullOnDelete();
            $table->foreignId('central_module_id')->nullable()->constrained('central_modules')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'is_public']);
            $table->index('route');
        });

        // Default menu-page-role mappings (used to provision new tenants)
        Schema::create('central_menu_page_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('central_menu_id')->constrained('central_menus')->cascadeOnDelete();
            $table->foreignId('central_page_id')->constrained('central_pages')->cascadeOnDelete();
            $table->string('role_slug', 50);
            $table->boolean('permission')->default(true);
            $table->integer('order')->default(0);
            $table->boolean('dropdown')->default(false);
            $table->boolean('lib_menu')->default(true);
            $table->timestamps();

            $table->unique(['central_menu_id', 'central_page_id', 'role_slug'], 'cmpd_menu_page_role_unique');
            $table->index('role_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_menu_page_defaults');
        Schema::dropIfExists('central_pages');
        Schema::dropIfExists('central_menus');
        Schema::dropIfExists('central_page_groups');
    }
};
