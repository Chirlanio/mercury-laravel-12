<?php

namespace App\Services;

use App\Enums\DamageMatchStatus;
use App\Enums\DamagedProductStatus;
use App\Enums\FootSide;
use App\Events\DamagedProductCreated;
use App\Models\DamagedProduct;
use App\Models\DamagedProductMatch;
use App\Models\DamagedProductPhoto;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRUD + regras de negócio do DamagedProduct.
 *
 * Mutação de status NÃO é responsabilidade deste serviço — usar
 * DamagedProductTransitionService. A criação aceita status apenas no estado
 * inicial (open).
 *
 * Dedup é feito via service (não via DB unique constraint) porque MySQL não
 * trata NULL=NULL em unique parcial — padrão consolidado em Coupons/Reversals.
 */
class DamagedProductService
{
    public function __construct(
        protected ImageUploadService $imageUploadService,
    ) {}

    /**
     * Cria novo registro com fotos opcionais.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>|null  $photos
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor, ?array $photos = null): DamagedProduct
    {
        $this->validateBusinessRules($data);
        $this->ensureUnique($data);
        $data = $this->autoFillFromCatalog($data);

        return DB::transaction(function () use ($data, $actor, $photos) {
            $product = DamagedProduct::create([
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'] ?? null,
                'product_reference' => $this->normalizeReference($data['product_reference']),
                'product_name' => $data['product_name'] ?? null,
                'product_color' => $data['product_color'] ?? null,
                'brand_cigam_code' => $data['brand_cigam_code'] ?? null,
                'product_size' => $data['product_size'] ?? null,
                'is_mismatched' => (bool) ($data['is_mismatched'] ?? false),
                'is_damaged' => (bool) ($data['is_damaged'] ?? false),
                'mismatched_foot' => $data['mismatched_foot'] ?? null,
                'mismatched_actual_size' => $data['mismatched_actual_size'] ?? null,
                'mismatched_expected_size' => $data['mismatched_expected_size'] ?? null,
                'damage_type_id' => $data['damage_type_id'] ?? null,
                'damaged_foot' => $data['damaged_foot'] ?? null,
                'damage_description' => $data['damage_description'] ?? null,
                'is_repairable' => (bool) ($data['is_repairable'] ?? false),
                'estimated_repair_cost' => $data['estimated_repair_cost'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => DamagedProductStatus::OPEN->value,
                'created_by_user_id' => $actor->id,
                'expires_at' => $data['expires_at'] ?? now()->addDays(90),
            ]);

            if ($photos) {
                $this->savePhotos($product, $photos, $actor);
            }

            DamagedProductCreated::dispatch($product->fresh(['photos', 'store', 'damageType']), $actor);

            return $product->fresh(['photos', 'store', 'damageType', 'createdBy']);
        });
    }

    /**
     * Atualiza um registro em estado não-final.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>|null  $newPhotos
     *
     * @throws ValidationException
     */
    public function update(DamagedProduct $product, array $data, User $actor, ?array $newPhotos = null): DamagedProduct
    {
        if ($product->isFinal()) {
            throw ValidationException::withMessages([
                'product' => 'Não é possível editar um produto avariado em estado final ('
                    . $product->status->label() . ').',
            ]);
        }

        // Snapshot dos atributos atuais com enums normalizados pra string —
        // validateBusinessRules() compara com in_array(..., true) que precisa
        // de tipo idêntico (enum object vs string string falha).
        $current = $product->only([
            'store_id', 'product_id', 'product_reference', 'is_mismatched', 'is_damaged',
            'mismatched_foot', 'mismatched_actual_size', 'mismatched_expected_size',
            'damage_type_id', 'damaged_foot',
        ]);
        foreach (['mismatched_foot', 'damaged_foot'] as $enumKey) {
            if (isset($current[$enumKey]) && $current[$enumKey] instanceof \BackedEnum) {
                $current[$enumKey] = $current[$enumKey]->value;
            }
        }

        $merged = array_merge($current, $data);

        $this->validateBusinessRules($merged);

        // Dedup ignora o próprio registro.
        $this->ensureUnique($merged, $product->id);

        $merged = $this->autoFillFromCatalog($merged);

        return DB::transaction(function () use ($product, $merged, $data, $actor, $newPhotos) {
            $product->update([
                'store_id' => $merged['store_id'],
                'product_id' => $merged['product_id'] ?? null,
                'product_reference' => $this->normalizeReference($merged['product_reference']),
                'product_name' => $merged['product_name'] ?? $product->product_name,
                'product_color' => $merged['product_color'] ?? $product->product_color,
                'brand_cigam_code' => $merged['brand_cigam_code'] ?? $product->brand_cigam_code,
                'product_size' => $merged['product_size'] ?? $product->product_size,
                'is_mismatched' => (bool) ($merged['is_mismatched'] ?? false),
                'is_damaged' => (bool) ($merged['is_damaged'] ?? false),
                'mismatched_foot' => $merged['mismatched_foot'] ?? null,
                'mismatched_actual_size' => $merged['mismatched_actual_size'] ?? null,
                'mismatched_expected_size' => $merged['mismatched_expected_size'] ?? null,
                'damage_type_id' => $merged['damage_type_id'] ?? null,
                'damaged_foot' => $merged['damaged_foot'] ?? null,
                'damage_description' => $data['damage_description'] ?? $product->damage_description,
                'is_repairable' => (bool) ($data['is_repairable'] ?? $product->is_repairable),
                'estimated_repair_cost' => $data['estimated_repair_cost'] ?? $product->estimated_repair_cost,
                'notes' => $data['notes'] ?? $product->notes,
                'updated_by_user_id' => $actor->id,
            ]);

            if ($newPhotos) {
                $this->savePhotos($product, $newPhotos, $actor);
            }

            return $product->fresh(['photos', 'store', 'damageType']);
        });
    }

    /**
     * Anexa novas fotos a um registro existente.
     *
     * @param  array<int,UploadedFile>  $photos
     */
    public function addPhotos(DamagedProduct $product, array $photos, User $actor): Collection
    {
        return DB::transaction(fn () => $this->savePhotos($product, $photos, $actor));
    }

    /**
     * Remove uma foto e apaga o arquivo do disco.
     */
    public function removePhoto(DamagedProductPhoto $photo): void
    {
        DB::transaction(function () use ($photo) {
            $this->imageUploadService->deleteFile($photo->file_path);
            $photo->delete();
        });
    }

    /**
     * Expira todos os matches PENDING vinculados ao produto. Usado quando o
     * produto entra em estado terminal (cancel/resolve manual).
     */
    public function expirePendingMatches(DamagedProduct $product): int
    {
        return DamagedProductMatch::query()
            ->forProduct($product->id)
            ->pending()
            ->update([
                'status' => DamageMatchStatus::EXPIRED->value,
                'resolved_at' => now(),
            ]);
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $data
     *
     * @throws ValidationException
     */
    protected function validateBusinessRules(array $data): void
    {
        $errors = [];

        $isMismatched = (bool) ($data['is_mismatched'] ?? false);
        $isDamaged = (bool) ($data['is_damaged'] ?? false);

        if (! $isMismatched && ! $isDamaged) {
            $errors['is_mismatched'] = 'Selecione ao menos um tipo de problema (par trocado ou avaria).';
        }

        if ($isMismatched) {
            if (empty($data['mismatched_foot'])) {
                $errors['mismatched_foot'] = 'Informe o pé com tamanho trocado.';
            } elseif (! in_array($data['mismatched_foot'], [FootSide::LEFT->value, FootSide::RIGHT->value], true)) {
                $errors['mismatched_foot'] = 'Pé inválido para par trocado (deve ser esquerdo ou direito).';
            }

            if (empty($data['mismatched_actual_size'])) {
                $errors['mismatched_actual_size'] = 'Informe o tamanho real do pé trocado.';
            }

            if (empty($data['mismatched_expected_size'])) {
                $errors['mismatched_expected_size'] = 'Informe o tamanho esperado do pé trocado.';
            }

            if (
                ! empty($data['mismatched_actual_size'])
                && ! empty($data['mismatched_expected_size'])
                && $data['mismatched_actual_size'] === $data['mismatched_expected_size']
            ) {
                $errors['mismatched_expected_size'] = 'O tamanho esperado deve ser diferente do tamanho real.';
            }
        }

        if ($isDamaged) {
            if (empty($data['damage_type_id'])) {
                $errors['damage_type_id'] = 'Informe o tipo de dano.';
            }

            if (empty($data['damaged_foot'])) {
                $errors['damaged_foot'] = 'Informe qual pé está avariado.';
            }
        }

        if (empty($data['store_id'])) {
            $errors['store_id'] = 'Loja é obrigatória.';
        }

        if (empty($data['product_reference'])) {
            $errors['product_reference'] = 'Referência do produto é obrigatória.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Bloqueia duplicata: mesmo (store, reference, sizes, foot) com registro
     * em status open/matched/transfer_requested. Permite re-cadastro após
     * resolved/cancelled.
     *
     * MySQL não suporta unique parcial respeitando NULL=NULL, por isso
     * checamos via query — padrão Coupons/Reversals.
     *
     * @param  array<string,mixed>  $data
     *
     * @throws ValidationException
     */
    protected function ensureUnique(array $data, ?int $ignoreId = null): void
    {
        $reference = $this->normalizeReference($data['product_reference'] ?? '');

        $query = DamagedProduct::query()
            ->where('store_id', $data['store_id'])
            ->where('product_reference', $reference)
            ->whereIn('status', [
                DamagedProductStatus::OPEN->value,
                DamagedProductStatus::MATCHED->value,
                DamagedProductStatus::TRANSFER_REQUESTED->value,
            ]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        // Critério adicional: mesmos detalhes de tamanho/pé (evita falso
        // positivo entre dois pares trocados de tamanhos diferentes).
        if (! empty($data['is_mismatched'])) {
            $query->where('is_mismatched', true)
                ->where('mismatched_foot', $data['mismatched_foot'] ?? null)
                ->where('mismatched_actual_size', $data['mismatched_actual_size'] ?? null)
                ->where('mismatched_expected_size', $data['mismatched_expected_size'] ?? null);
        } elseif (! empty($data['is_damaged'])) {
            $query->where('is_damaged', true)
                ->where('damaged_foot', $data['damaged_foot'] ?? null);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'product_reference' => 'Já existe um registro aberto para este produto, loja e tipo de problema.',
            ]);
        }
    }

    /**
     * Quando product_id está informado (ou conseguimos resolver via reference),
     * preenche brand/color/name a partir do catálogo. Editável depois.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function autoFillFromCatalog(array $data): array
    {
        $product = null;

        if (! empty($data['product_id'])) {
            $product = Product::find($data['product_id']);
        } elseif (! empty($data['product_reference'])) {
            $product = Product::where('reference', $this->normalizeReference($data['product_reference']))->first();
            if ($product) {
                $data['product_id'] = $product->id;
            }
        }

        if ($product) {
            $data['product_name'] ??= $product->description;
            $data['brand_cigam_code'] ??= $product->brand_cigam_code;
            $data['product_color'] ??= $product->color_cigam_code;
        }

        return $data;
    }

    protected function normalizeReference(string $reference): string
    {
        return Str::upper(trim($reference));
    }

    /**
     * @param  array<int,UploadedFile>  $photos
     */
    protected function savePhotos(DamagedProduct $product, array $photos, User $actor): Collection
    {
        $directory = "damaged-products/{$product->ulid}";
        $created = collect();

        foreach ($photos as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $this->imageUploadService->uploadImage($file, $directory);

            $created->push(DamagedProductPhoto::create([
                'damaged_product_id' => $product->id,
                'filename' => basename($path),
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'sort_order' => $product->photos()->max('sort_order') + 1,
                'uploaded_by_user_id' => $actor->id,
            ]));
        }

        return $created;
    }
}
