<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->tinyInteger('reference_month');
            $table->smallInteger('reference_year');
            $table->decimal('goal_amount', 12, 2);
            $table->decimal('super_goal', 12, 2);
            $table->integer('business_days');
            $table->integer('non_working_days')->default(0);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['store_id', 'reference_month', 'reference_year'], 'store_goals_unique');
        });

        Schema::create('consultant_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_goal_id');
            $table->unsignedBigInteger('employee_id');
            $table->tinyInteger('reference_month');
            $table->smallInteger('reference_year');
            $table->integer('working_days');
            $table->integer('business_days');
            $table->integer('deducted_days')->default(0);
            $table->decimal('individual_goal', 12, 2);
            $table->decimal('super_goal', 12, 2);
            $table->decimal('hiper_goal', 12, 2);
            $table->string('level_snapshot', 20);
            $table->decimal('weight', 4, 2);
            $table->timestamps();

            $table->foreign('store_goal_id')->references('id')->on('store_goals')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['store_goal_id', 'employee_id'], 'consultant_goals_unique');
            $table->index(['reference_month', 'reference_year']);
        });

        Schema::create('percentage_awards', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->unique();
            $table->decimal('no_goal_pct', 5, 2);
            $table->decimal('goal_pct', 5, 2);
            $table->decimal('super_goal_pct', 5, 2);
            $table->decimal('hiper_goal_pct', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_goals');
        Schema::dropIfExists('store_goals');
        Schema::dropIfExists('percentage_awards');
    }
};
