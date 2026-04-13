<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relax the hd_interactions.type enum to a string to allow new types
     * (sla_warning, sla_breach) without further schema churn.
     */
    public function up(): void
    {
        if (! Schema::hasTable('hd_interactions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE hd_interactions MODIFY COLUMN type VARCHAR(30) NOT NULL DEFAULT 'comment'");

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite: enum is a CHECK constraint on the column. We rebuild the table
            // under the SAME name so that FKs in hd_attachments (which reference
            // "hd_interactions" by name) keep working after migration.
            DB::statement('PRAGMA foreign_keys = OFF');

            // Preserve existing rows in a plain table (no FK, so no dangling refs).
            DB::statement('CREATE TABLE _hd_interactions_backup AS SELECT * FROM hd_interactions');

            // Drop original (and its indexes).
            Schema::drop('hd_interactions');

            // Recreate with relaxed type column.
            Schema::create('hd_interactions', function ($table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('hd_tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->text('comment')->nullable();
                $table->string('type', 30)->default('comment');
                $table->string('old_value')->nullable();
                $table->string('new_value')->nullable();
                $table->boolean('is_internal')->default(false);
                $table->timestamps();

                $table->index(['ticket_id', 'created_at']);
            });

            // Restore data, if any.
            DB::statement('INSERT INTO hd_interactions (id, ticket_id, user_id, comment, type, old_value, new_value, is_internal, created_at, updated_at)
                SELECT id, ticket_id, user_id, comment, type, old_value, new_value, is_internal, created_at, updated_at FROM _hd_interactions_backup');

            DB::statement('DROP TABLE _hd_interactions_backup');
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        // Postgres / others: use change() as a best-effort
        Schema::table('hd_interactions', function ($table) {
            $table->string('type', 30)->default('comment')->change();
        });
    }

    public function down(): void
    {
        // No-op: we don't re-introduce the restrictive enum.
    }
};
