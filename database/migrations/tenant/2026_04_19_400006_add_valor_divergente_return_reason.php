<?php

use App\Enums\ReturnReasonCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona o motivo "Valor cobrado incorreto" ao catálogo de devoluções.
 *
 * Caso comum no e-commerce: cliente foi cobrado a mais, desconto/cupom
 * não foi aplicado, ou frete errado. Faz parte da categoria DIVERGENCIA.
 *
 * Migration idempotente — só insere se não existir.
 */
return new class extends Migration
{
    private const CODE = 'DIV_VALOR';

    public function up(): void
    {
        $exists = DB::table('return_reasons')->where('code', self::CODE)->exists();

        if ($exists) {
            return;
        }

        DB::table('return_reasons')->insert([
            'code' => self::CODE,
            'name' => 'Valor cobrado incorreto',
            'category' => ReturnReasonCategory::DIVERGENCIA->value,
            'description' => 'Cliente foi cobrado a mais, desconto/cupom não foi aplicado ou frete errado.',
            'is_active' => true,
            'sort_order' => 75, // Entre DIV_MODELO (70) e DIV_QTD (80)
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('return_reasons')->where('code', self::CODE)->delete();
    }
};
