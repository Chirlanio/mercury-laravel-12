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
        Schema::create('access_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->integer('order');
            $table->foreignId('color_theme_id')->nullable()->constrained('color_themes')->onDelete('set null');
            $table->timestamps();

            $table->index('name');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_levels');
    }
};
