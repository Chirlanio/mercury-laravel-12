<?php

namespace App\Services\DRE;

use Illuminate\Http\Request;

/**
 * DTO simples dos filtros aceitos por `DreMappingService::list()`.
 *
 * Mantido como objeto (em vez de array associativo) só pra dar clareza
 * às chamadas em múltiplos pontos (controller + tests). O projeto não
 * usa DTOs formais em outros módulos; este é um caso isolado justificado
 * pela quantidade de filtros independentes.
 */
class DreMappingListFilter
{
    public function __construct(
        public ?string $search = null,
        public ?int $accountGroup = null,
        public ?int $costCenterId = null,
        public ?int $managementLineId = null,
        public bool $onlyUnmapped = false,
        /** Data efetiva usada em scopes temporais ('Y-m-d'). Null = hoje. */
        public ?string $effectiveOn = null,
        public int $perPage = 25,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->trim()->toString() ?: null,
            accountGroup: $request->has('account_group') && $request->input('account_group') !== '' && $request->input('account_group') !== null
                ? (int) $request->input('account_group')
                : null,
            costCenterId: $request->has('cost_center_id') && $request->input('cost_center_id') !== '' && $request->input('cost_center_id') !== null
                ? (int) $request->input('cost_center_id')
                : null,
            managementLineId: $request->has('management_line_id') && $request->input('management_line_id') !== '' && $request->input('management_line_id') !== null
                ? (int) $request->input('management_line_id')
                : null,
            onlyUnmapped: $request->boolean('only_unmapped'),
            effectiveOn: $request->input('effective_on') ?: null,
            perPage: (int) $request->input('per_page', 25),
        );
    }
}
