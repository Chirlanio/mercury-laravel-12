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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('cpf')->unique();
            $table->date('admission_date');
            $table->date('dismissal_date')->nullable();
            $table->string('position_id')->nullable();
            $table->string('site_coupon')->nullable();
            $table->string('store_id')->nullable();
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->unsignedBigInteger('gender_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->boolean('is_pcd')->default(false);
            $table->boolean('is_apprentice')->default(false);
            $table->string('level')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'short_name', 'profile_image', 'cpf', 'admission_date',
                'dismissal_date', 'position_id', 'site_coupon', 'store_id',
                'education_level_id', 'gender_id', 'birth_date', 'area_id',
                'is_pcd', 'is_apprentice', 'level', 'status_id'
            ]);
        });
    }
};
