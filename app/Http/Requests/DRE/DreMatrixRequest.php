<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtro canônico da matriz DRE.
 *
 * Campos:
 *   - start_date, end_date (Y-m-d)
 *   - store_ids[] / network_ids[] (opcionais — filtra escopo)
 *   - budget_version (string, default 'v1')
 *   - scope: general | network | store
 *   - include_unclassified (bool default true)
 *   - compare_previous_year (bool default true)
 *
 * Autorização: exige `VIEW_DRE`. Scope `store` com `store_ids` fora do
 * `allowedStores()` do user resulta em 403 — preferimos erro visível a
 * filtrar silenciosamente (erros de escopo devem ser explícitos).
 */
class DreMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->hasPermissionTo(Permission::VIEW_DRE->value)) {
            return false;
        }

        $scope = $this->input('scope', 'general');
        if ($scope === 'store') {
            $requested = array_map('intval', (array) $this->input('store_ids', []));
            if (! empty($requested) && method_exists($user, 'allowedStores')) {
                $allowed = $user->allowedStores()->pluck('id')->map(fn ($i) => (int) $i)->all();
                foreach ($requested as $id) {
                    if (! in_array($id, $allowed, true)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',

            'store_ids' => 'nullable|array',
            'store_ids.*' => 'integer|exists:stores,id',

            'network_ids' => 'nullable|array',
            'network_ids.*' => 'integer|exists:networks,id',

            'budget_version' => 'nullable|string|max:30',

            'scope' => ['nullable', Rule::in(['general', 'network', 'store'])],

            'include_unclassified' => 'nullable|boolean',
            'compare_previous_year' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Informe a data inicial do período.',
            'start_date.date_format' => 'Data inicial inválida — use o formato AAAA-MM-DD.',
            'end_date.required' => 'Informe a data final do período.',
            'end_date.date_format' => 'Data final inválida — use o formato AAAA-MM-DD.',
            'end_date.after_or_equal' => 'A data final deve ser maior ou igual à inicial.',
            'store_ids.*.exists' => 'Uma das lojas informadas não existe.',
            'network_ids.*.exists' => 'Uma das redes informadas não existe.',
            'scope.in' => 'Escopo inválido — use "general", "network" ou "store".',
        ];
    }

    /**
     * Filtro normalizado com defaults aplicados. Usado tanto pelo Controller
     * (ainda não existe — prompt 7) quanto pelos testes do DreMatrixService.
     *
     * @return array{
     *   start_date: string,
     *   end_date: string,
     *   store_ids: array<int,int>,
     *   network_ids: array<int,int>,
     *   budget_version: ?string,
     *   scope: string,
     *   include_unclassified: bool,
     *   compare_previous_year: bool
     * }
     */
    public function normalized(): array
    {
        return [
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
            'store_ids' => array_map('intval', (array) ($this->input('store_ids') ?? [])),
            'network_ids' => array_map('intval', (array) ($this->input('network_ids') ?? [])),
            'budget_version' => $this->input('budget_version'),
            'scope' => $this->input('scope', 'general'),
            'include_unclassified' => filter_var(
                $this->input('include_unclassified', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? true,
            'compare_previous_year' => filter_var(
                $this->input('compare_previous_year', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? true,
        ];
    }
}
