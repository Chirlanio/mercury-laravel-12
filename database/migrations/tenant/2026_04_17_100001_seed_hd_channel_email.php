<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the `email` channel row so the EmailIntakeDriver has a channel
 * to attach tickets to.
 *
 * Config shape:
 *   {
 *     "addresses": {
 *       "ti@helpdesk.meiasola.com.br": 1,
 *       "rh@helpdesk.meiasola.com.br": 2
 *     },
 *     "default_department_id": 1,
 *     "max_attachment_size_mb": 10
 *   }
 *
 * Tenant admins are expected to fill this map via the DepartmentSettings UI
 * (or directly via an artisan command) after the first deploy. Until filled,
 * the driver falls back to default_department_id and, if that is also null,
 * refuses the webhook with a hard error so the operator notices.
 *
 * Idempotent: only inserts when the row is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_channels')) {
            return;
        }

        $exists = DB::table('hd_channels')->where('slug', 'email')->exists();
        if ($exists) {
            return;
        }

        DB::table('hd_channels')->insert([
            'slug' => 'email',
            'name' => 'E-mail',
            'driver' => 'email',
            'config' => json_encode([
                'addresses' => new \stdClass(),
                'default_department_id' => null,
                'max_attachment_size_mb' => 10,
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_channels')) {
            return;
        }

        DB::table('hd_channels')->where('slug', 'email')->delete();
    }
};
