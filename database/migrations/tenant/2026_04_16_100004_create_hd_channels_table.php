<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_channels')) {
            Schema::create('hd_channels', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 50)->unique();
                $table->string('name', 120);
                // Driver slug — resolves to an IntakeDriverInterface implementation.
                // Initial values: 'web', 'whatsapp', 'email'.
                $table->string('driver', 40);
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('driver');
            });
        }

        // Seed the default web channel so existing ticket creation has a channel
        // to attach to. Idempotent — only inserts when missing.
        $exists = DB::table('hd_channels')->where('slug', 'web')->exists();
        if (! $exists) {
            DB::table('hd_channels')->insert([
                'slug' => 'web',
                'name' => 'Web',
                'driver' => 'web',
                'config' => json_encode([]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_channels');
    }
};
