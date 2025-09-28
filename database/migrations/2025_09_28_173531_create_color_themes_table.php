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
        Schema::create('color_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('color_class', 40);
            $table->timestamps();

            $table->index('name');
            $table->index('color_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('color_themes');
    }
};
