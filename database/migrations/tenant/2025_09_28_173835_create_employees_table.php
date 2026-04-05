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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('short_name', 40);
            $table->string('profile_image', 150)->nullable();
            $table->string('cpf', 11)->unique();
            $table->date('admission_date');
            $table->date('dismissal_date')->nullable();
            $table->unsignedBigInteger('position_id');
            $table->string('site_coupon', 25)->nullable();
            $table->string('store_id', 4);
            $table->unsignedBigInteger('education_level_id');
            $table->unsignedBigInteger('gender_id');
            $table->date('birth_date');
            $table->unsignedBigInteger('area_id');
            $table->boolean('is_pcd')->default(false);
            $table->boolean('is_apprentice')->default(false);
            $table->enum('level', ['Junior', 'Pleno', 'Senior'])->default('Junior');
            $table->unsignedBigInteger('status_id')->default(2);
            $table->timestamps();

            // Indexes
            $table->index('cpf');
            $table->index('position_id');
            $table->index('store_id');
            $table->index('education_level_id');
            $table->index('gender_id');
            $table->index('area_id');
            $table->index('status_id');
            $table->index('admission_date');
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
