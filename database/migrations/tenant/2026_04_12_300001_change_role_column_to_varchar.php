<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite não suporta ALTER MODIFY — column já é TEXT no SQLite
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE `users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('super_admin','admin','support','user') NOT NULL DEFAULT 'user'");
        }
    }
};
