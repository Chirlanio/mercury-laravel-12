<?php

namespace App\Models;

use App\Enums\AccountGroup;
use App\Enums\AccountingNature;
use App\Enums\AccountType;
use App\Enums\DreGroup;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plano de Contas Contábil (Chart of Accounts) — base do DRE + orçamentos.
 *
 * Tabela renomeada de `accounting_classes` para `chart_of_accounts` no
 * prompt #1 do DRE. Colunas novas desse prompt:
 *   - reduced_code: chave curta do ERP (ex: "1191"), estável entre
 *     reimportações. Usada como upsert key no ChartOfAccountsImporter.
 *   - account_group: 1..5 (Ativo/Passivo/Receitas/Custos/Resultado).
 *     Derivado do primeiro segmento do code.
 *   - classification_level: número de pontos no code (0..4).
 *   - is_result_account: true para grupos 3, 4, 5.
 *   - default_management_class_id: sugestão pré-populada (do Action Plan v1);
 *     não é de-para formal do DRE — é hint pra UI de Pendências.
 *   - external_source, imported_at: rastreabilidade do ERP.
 *
 * Retrocompat: a classe `AccountingClass` (em App\Models\AccountingClass)
 * vira alias fino `extends ChartOfAccount` para não quebrar ~30 arquivos
 * que referenciam o nome antigo. Prompts futuros migram gradualmente.
 *
 * Hierarquia via `parent_id` (self). Sintéticos (`accepts_entries=false`)
 * totalizam; analíticas (folhas) recebem lançamento.
 *
 * Soft delete manual (padrão do projeto).
 */
class ChartOfAccount extends Model
{
    use Auditable, HasFactory;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'reduced_code',
        'name',
        'type',
        'account_group',
        'classification_level',
        'is_result_account',
        'description',
        'parent_id',
        'nature',
        'balance_nature',
        'dre_group',
        'accepts_entries',
        'sort_order',
        'is_active',
        'default_management_class_id',
        'external_source',
        'imported_at',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'nature' => AccountingNature::class,
        'dre_group' => DreGroup::class,
        'account_group' => AccountGroup::class,
        'accepts_entries' => 'boolean',
        'is_active' => 'boolean',
        'is_result_account' => 'boolean',
        'sort_order' => 'integer',
        'classification_level' => 'integer',
        'imported_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function defaultManagementClass(): BelongsTo
    {
        return $this->belongsTo(ManagementClass::class, 'default_management_class_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(DreMapping::class, 'chart_of_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeLeaves(Builder $query): Builder
    {
        return $query->where('accepts_entries', true);
    }

    public function scopeSyntheticGroups(Builder $query): Builder
    {
        return $query->where('accepts_entries', false);
    }

    public function scopeByDreGroup(Builder $query, DreGroup|string $group): Builder
    {
        $value = $group instanceof DreGroup ? $group->value : $group;

        return $query->where('dre_group', $value);
    }

    public function scopeByAccountGroup(Builder $query, int|AccountGroup $group): Builder
    {
        $value = $group instanceof AccountGroup ? $group->value : $group;

        return $query->where('account_group', $value);
    }

    /**
     * Alias curto para `scopeByAccountGroup`. Pedido pelo prompt #2.
     */
    public function scopeByGroup(Builder $query, int|AccountGroup $group): Builder
    {
        return $this->scopeByAccountGroup($query, $group);
    }

    /**
     * Filtra descendentes de um code usando prefix match. Equivalente a
     * "todas as contas abaixo de X.Y na árvore", sem precisar de CTE.
     *
     * Ex: `->childrenOf('3.1.1')` retorna contas cujo code começa com
     * "3.1.1." (ponto final preservado para não pegar falso-positivo
     * tipo "3.1.10"). A conta "3.1.1" em si não é incluída — para
     * incluir use `->where('code', '3.1.1')->orWhere(...)` manualmente.
     */
    public function scopeChildrenOf(Builder $query, string $code): Builder
    {
        return $query->where('code', 'like', $code.'.%');
    }

    public function scopeAnalytical(Builder $query): Builder
    {
        // Prefere a coluna nova `type` quando populada; cai em
        // `accepts_entries` para retrocompat com dados antigos.
        return $query->where(function (Builder $q) {
            $q->where('type', AccountType::ANALYTICAL->value)
                ->orWhere(function (Builder $inner) {
                    $inner->whereNull('type')->where('accepts_entries', true);
                });
        });
    }

    public function scopeSynthetic(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('type', AccountType::SYNTHETIC->value)
                ->orWhere(function (Builder $inner) {
                    $inner->whereNull('type')->where('accepts_entries', false);
                });
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('code', 'like', $like)
                ->orWhere('reduced_code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isLeaf(): bool
    {
        return (bool) $this->accepts_entries;
    }

    public function isSyntheticGroup(): bool
    {
        return ! $this->accepts_entries;
    }

    /**
     * @return array<int, int>
     */
    public function ancestorsIds(): array
    {
        $ids = [];
        $current = $this->parent;
        while ($current && ! in_array($current->id, $ids, true)) {
            $ids[] = $current->id;
            $current = $current->parent;
        }

        return $ids;
    }

    public function followsNaturalNature(): bool
    {
        if (! $this->nature || ! $this->dre_group) {
            return true;
        }

        return $this->nature === $this->dre_group->naturalNature();
    }

    /**
     * Deriva `account_group`, `classification_level` e `is_result_account`
     * a partir do `code`. Usado pelo ChartOfAccountsImporter e pelo
     * backfill da migration.
     *
     * @return array{account_group: ?int, classification_level: int, is_result_account: bool}
     */
    public static function deriveFromCode(string $code): array
    {
        $firstSegment = explode('.', $code)[0] ?? '';
        $group = ctype_digit($firstSegment) ? (int) $firstSegment : null;
        $level = substr_count($code, '.');
        $isResult = in_array($group, [3, 4, 5], true);

        return [
            'account_group' => $group,
            'classification_level' => $level,
            'is_result_account' => $isResult,
        ];
    }
}
