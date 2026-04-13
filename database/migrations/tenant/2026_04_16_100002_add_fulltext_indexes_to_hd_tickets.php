<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FULLTEXT indexes are a MySQL/MariaDB feature. SQLite (tests) silently
        // skips this migration and falls back to LIKE searches.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('hd_tickets') && ! $this->indexExists('hd_tickets', 'hd_tickets_title_description_fulltext')) {
            DB::statement('ALTER TABLE hd_tickets ADD FULLTEXT INDEX hd_tickets_title_description_fulltext (title, description)');
        }

        if (Schema::hasTable('hd_interactions') && ! $this->indexExists('hd_interactions', 'hd_interactions_comment_fulltext')) {
            DB::statement('ALTER TABLE hd_interactions ADD FULLTEXT INDEX hd_interactions_comment_fulltext (comment)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('hd_tickets') && $this->indexExists('hd_tickets', 'hd_tickets_title_description_fulltext')) {
            DB::statement('ALTER TABLE hd_tickets DROP INDEX hd_tickets_title_description_fulltext');
        }

        if (Schema::hasTable('hd_interactions') && $this->indexExists('hd_interactions', 'hd_interactions_comment_fulltext')) {
            DB::statement('ALTER TABLE hd_interactions DROP INDEX hd_interactions_comment_fulltext');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::select(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );

        return ($result[0]->c ?? 0) > 0;
    }
};
