<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Estende `chart_of_accounts` com as colunas exigidas pelo módulo DRE:
 *
 *   - reduced_code: código curto do ERP (ex: "1191"). Chave estável entre
 *     reimportações. Pode conflitar se seed antigo não tiver; permitido
 *     null por enquanto, unique aplicada só quando populado.
 *   - account_group: tinyint 1..5 (Ativo/Passivo/Receitas/Custos/Resultado).
 *     Derivado do primeiro segmento do code.
 *   - classification_level: tinyint 0..4. Número de pontos no code.
 *   - is_result_account: boolean. true para grupos 3, 4, 5.
 *   - default_management_class_id: FK opcional para management_classes.
 *     Sugestão para pré-popular a UI de Pendências de mapping DRE.
 *   - external_source: 'CIGAM' / 'TAYLOR' / 'ZZNET'. Nullable.
 *   - imported_at: timestamp da última importação que tocou a linha.
 *
 * Backfill:
 * - O seed atual (80 analíticas + 21 sintéticas) tem codes no formato
 *   "X.X.X.XX.XXXXX" (5 segmentos na folha). Derivamos account_group
 *   e classification_level a partir do code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('reduced_code', 20)->nullable()->after('code');
            $table->unsignedTinyInteger('account_group')->nullable()->after('name');
            $table->unsignedTinyInteger('classification_level')->nullable()->after('account_group');
            $table->boolean('is_result_account')->default(false)->after('classification_level');

            $table->unsignedBigInteger('default_management_class_id')
                ->nullable()
                ->after('is_active');

            $table->string('external_source', 20)->nullable()->after('default_management_class_id');
            $table->timestamp('imported_at')->nullable()->after('external_source');
        });

        // FK para management_classes só pode ser criada depois que a coluna
        // existe; a FK efetiva vai na migration seguinte porque
        // management_classes já está criada antes desta.
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreign('default_management_class_id')
                ->references('id')
                ->on('management_classes')
                ->nullOnDelete();

            // reduced_code unique quando não null: em MySQL 8 e SQLite 3.8+
            // suportamos índice único com expressão, mas Laravel não expõe
            // partial index portátil. Usamos unique simples — duplicatas
            // precisam ser evitadas no import (já é a convenção). Múltiplos
            // nulls são permitidos por padrão.
            $table->unique('reduced_code', 'chart_of_accounts_reduced_code_unique');

            $table->index('account_group');
            $table->index(['account_group', 'classification_level']);
            $table->index(['account_group', 'is_active'], 'chart_of_accounts_group_active_idx');
        });

        // Backfill ----------------------------------------------------------

        $rows = DB::table('chart_of_accounts')
            ->select('id', 'code')
            ->get();

        foreach ($rows as $row) {
            $code = (string) $row->code;
            $firstSegment = explode('.', $code)[0] ?? '';
            $group = ctype_digit($firstSegment) ? (int) $firstSegment : null;
            $level = substr_count($code, '.');
            $isResult = in_array($group, [3, 4, 5], true);

            DB::table('chart_of_accounts')
                ->where('id', $row->id)
                ->update([
                    'account_group' => $group,
                    'classification_level' => $level,
                    'is_result_account' => $isResult,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex('chart_of_accounts_group_active_idx');
            $table->dropIndex(['account_group', 'classification_level']);
            $table->dropIndex(['account_group']);
            $table->dropUnique('chart_of_accounts_reduced_code_unique');
            $table->dropForeign(['default_management_class_id']);
            $table->dropColumn([
                'reduced_code',
                'account_group',
                'classification_level',
                'is_result_account',
                'default_management_class_id',
                'external_source',
                'imported_at',
            ]);
        });
    }
};
