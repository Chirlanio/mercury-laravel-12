<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('controller', 220);
            $table->string('method', 220);
            $table->string('menu_controller', 220);
            $table->string('menu_method', 220);
            $table->string('page_name', 220);
            $table->mediumText('notes');
            $table->boolean('is_public')->default(false);
            $table->string('icon', 40)->nullable();
            $table->foreignId('page_group_id')->constrained('page_groups')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['controller', 'method']);
            $table->index(['menu_controller', 'menu_method']);
            $table->index('page_name');
            $table->index('is_public');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
