<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_content_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('icon', 50)->default('AcademicCapIcon');
            $table->string('color', 20)->default('primary');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });

        // Seed default categories
        $now = now();
        $categories = [
            ['name' => 'Onboarding', 'icon' => 'UserPlusIcon', 'color' => 'info'],
            ['name' => 'Produto', 'icon' => 'CubeIcon', 'color' => 'primary'],
            ['name' => 'Processo', 'icon' => 'CogIcon', 'color' => 'warning'],
            ['name' => 'Compliance', 'icon' => 'ShieldCheckIcon', 'color' => 'danger'],
            ['name' => 'Soft Skills', 'icon' => 'ChatBubbleLeftRightIcon', 'color' => 'success'],
            ['name' => 'Técnico', 'icon' => 'WrenchScrewdriverIcon', 'color' => 'gray'],
        ];

        foreach ($categories as $cat) {
            DB::table('training_content_categories')->insert(array_merge($cat, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_content_categories');
    }
};
