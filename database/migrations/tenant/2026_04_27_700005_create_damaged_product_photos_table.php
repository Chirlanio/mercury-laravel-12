<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damaged_product_id')->constrained('damaged_products')->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('original_filename', 255)->nullable();
            $table->string('file_path', 500); // caminho relativo no disk public
            $table->unsignedInteger('file_size')->nullable(); // bytes
            $table->string('mime_type', 50)->nullable();
            $table->string('caption', 255)->nullable(); // melhoria v2 — legenda opcional
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('damaged_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_product_photos');
    }
};
