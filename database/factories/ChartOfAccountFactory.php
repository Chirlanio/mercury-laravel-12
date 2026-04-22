<?php

namespace Database\Factories;

use App\Enums\AccountGroup;
use App\Enums\AccountingNature;
use App\Enums\AccountType;
use App\Enums\DreGroup;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        // Gera code de 5 segmentos (level 4 = analítica folha).
        $code = fake()->unique()->numerify('#.#.#.##.#####');

        return [
            'code' => $code,
            'reduced_code' => fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
            'type' => AccountType::ANALYTICAL->value,
            'description' => null,
            'parent_id' => null,
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value,
            'accepts_entries' => true,
            'account_group' => AccountGroup::CUSTOS_DESPESAS->value,
            'classification_level' => 4,
            'is_result_account' => true,
            'sort_order' => 0,
            'is_active' => true,
            'default_management_class_id' => null,
            'external_source' => null,
            'imported_at' => null,
        ];
    }

    public function analytical(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::ANALYTICAL->value,
            'accepts_entries' => true,
        ]);
    }

    public function synthetic(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::SYNTHETIC->value,
            'accepts_entries' => false,
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn () => [
            'nature' => AccountingNature::CREDIT->value,
            'dre_group' => DreGroup::RECEITA_BRUTA->value,
            'account_group' => AccountGroup::RECEITAS->value,
            'is_result_account' => true,
        ]);
    }

    /**
     * Gera a conta no nível hierárquico informado (0..4), ajustando o
     * `classification_level` e gerando um `code` com o número
     * correspondente de segmentos.
     *
     *   0 → "3"                    (grupo macro)
     *   1 → "3.1"
     *   2 → "3.1.1"
     *   3 → "3.1.1.01"
     *   4 → "3.1.1.01.00012"       (folha analítica — padrão ERP)
     */
    public function atLevel(int $level): static
    {
        if ($level < 0 || $level > 4) {
            throw new \InvalidArgumentException("atLevel só aceita 0..4, recebeu {$level}.");
        }

        // Usa prefixo "9" (fora do range de seeds reais 1-5) + números
        // randomizados para evitar colisão de code UNIQUE com o seed existente.
        $topUniq = fake()->unique()->numberBetween(10, 99999);
        $segments = match ($level) {
            0 => [9],
            1 => [9, (int) ('9'.str_pad((string) ($topUniq % 100), 2, '0', STR_PAD_LEFT))],
            2 => [9, 9, (int) ('9'.str_pad((string) ($topUniq % 100), 2, '0', STR_PAD_LEFT))],
            3 => [
                9,
                9,
                9,
                str_pad((string) ($topUniq % 1000), 3, '0', STR_PAD_LEFT),
            ],
            4 => [
                9,
                9,
                9,
                str_pad((string) (($topUniq / 100) % 100), 2, '0', STR_PAD_LEFT),
                str_pad((string) ($topUniq % 100000), 5, '0', STR_PAD_LEFT),
            ],
        };

        $code = implode('.', $segments);

        // Analíticas (folhas) são level 4; níveis abaixo são sintéticos.
        $isLeaf = $level === 4;

        // `account_group` não é derivado do segmento porque usamos prefixo
        // "9" (fora do seed real 1..5) para evitar colisão — AccountGroup
        // enum só aceita 1..5, então respeitamos o valor padrão da
        // definition (4 = CUSTOS_DESPESAS) a menos que o caller passe
        // explicitamente.
        return $this->state(fn () => [
            'code' => $code,
            'classification_level' => $level,
            'type' => $isLeaf ? AccountType::ANALYTICAL->value : AccountType::SYNTHETIC->value,
            'accepts_entries' => $isLeaf,
        ]);
    }
}
