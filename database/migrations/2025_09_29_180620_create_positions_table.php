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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('level', 40)->nullable();
            $table->unsignedBigInteger('level_category_id');
            $table->unsignedBigInteger('status_id')->default(1);
            $table->timestamps();

            $table->index(['status_id']);
            $table->index(['level_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
