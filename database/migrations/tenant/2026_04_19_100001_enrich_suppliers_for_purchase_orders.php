<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'payment_terms_default')) {
                $table->string('payment_terms_default', 150)->nullable()->after('email');
            }
            if (! Schema::hasColumn('suppliers', 'address')) {
                $table->string('address')->nullable()->after('payment_terms_default');
            }
            if (! Schema::hasColumn('suppliers', 'city')) {
                $table->string('city', 120)->nullable()->after('address');
            }
            if (! Schema::hasColumn('suppliers', 'state')) {
                $table->string('state', 2)->nullable()->after('city');
            }
            if (! Schema::hasColumn('suppliers', 'zip')) {
                $table->string('zip', 10)->nullable()->after('state');
            }
            if (! Schema::hasColumn('suppliers', 'notes')) {
                $table->text('notes')->nullable()->after('zip');
            }
            if (! Schema::hasColumn('suppliers', 'deleted_at')) {
                $table->softDeletes()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            foreach (['deleted_at', 'notes', 'zip', 'state', 'city', 'address', 'payment_terms_default'] as $col) {
                if (Schema::hasColumn('suppliers', $col)) {
                    if ($col === 'deleted_at') {
                        $table->dropSoftDeletes();
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
