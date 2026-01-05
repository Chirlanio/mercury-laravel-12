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
        Schema::table('color_themes', function (Blueprint $table) {
            $table->string('hex_color', 7)->nullable()->after('color_class');
        });

        // Atualizar cores existentes com valores hex padrao
        $defaultColors = [
            'primary' => '#3B82F6',
            'secondary' => '#6B7280',
            'success' => '#22C55E',
            'danger' => '#EF4444',
            'warning' => '#F59E0B',
            'info' => '#06B6D4',
            'light' => '#F3F4F6',
            'dark' => '#1F2937',
        ];

        foreach ($defaultColors as $colorClass => $hexColor) {
            \DB::table('color_themes')
                ->where('color_class', $colorClass)
                ->update(['hex_color' => $hexColor]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('color_themes', function (Blueprint $table) {
            $table->dropColumn('hex_color');
        });
    }
};
