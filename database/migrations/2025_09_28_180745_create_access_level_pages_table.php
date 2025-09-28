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
        Schema::create('access_level_pages', function (Blueprint $table) {
            $table->id();
            $table->boolean('permission')->default(true);
            $table->integer('order');
            $table->boolean('dropdown')->default(false);
            $table->boolean('lib_menu')->default(false);
            $table->foreignId('menu_id')->nullable()->constrained('menus')->onDelete('set null');

            // Altere para unsignedInteger para corresponder ao tipo de 'increments()'
            $table->unsignedBigInteger('access_level_id');
            $table->foreign('access_level_id')->references('id')->on('access_levels')->onDelete('cascade');

            $table->foreignId('page_id')->constrained('pages')->onDelete('cascade');
            $table->timestamps();

            $table->index('permission');
            $table->index('order');
            $table->index('dropdown');
            $table->index('lib_menu');
            $table->index(['access_level_id', 'page_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_level_pages');
    }
};
