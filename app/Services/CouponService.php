<?php

namespace App\Services;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de cupons. Não manipula status além da criação (draft inicial,
 * com opção de transicionar auto pra requested via autoRequest).
 * Todas as transições subsequentes vão pelo CouponTransitionService.
 */
class CouponService
{
    public function __construct(
        protected CouponLookupService $lookup,
        protected CouponTransitionService $transition,
    ) {}

    /**
     * Cria um cupom + valida regras por tipo + unicidade + opcional
     * auto-transição pra requested (dispara notificação e-commerce).
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor, bool $autoRequest = true): Coupon
    {
        $type = CouponType::from($data['type']);

        $this->validateTypeRules($type, $data);

        $cpf = $data['cpf'];
        $cpfHash = Coupon::hashCpf($cpf);
        $storeCode = $type->requiresStoreAndEmployee() ? ($data['store_code'] ?? null) : null;

        $this->ensureUnique($cpfHash, $type, $storeCode);

        $coupon = DB::transaction(function () use ($data, $type, $actor, $storeCode) {
            $coupon = Coupon::create([
                'type' => $type->value,
                'status' => CouponStatus::DRAFT->value,
                'employee_id' => $type->requiresStoreAndEmployee() ? ($data['employee_id'] ?? null) : null,
                'store_code' => $storeCode,
                'influencer_name' => $type === CouponType::INFLUENCER
                    ? ($data['influencer_name'] ?? null)
                    : null,
                'cpf' => $data['cpf'],
                'social_media_id' => $type->requiresInfluencerFields()
                    ? ($data['social_media_id'] ?? null)
                    : null,
                'social_media_link' => $type->requiresInfluencerFields()
                    ? ($data['social_media_link'] ?? null)
                    : null,
                'city' => $type->requiresInfluencerFields() ? ($data['city'] ?? null) : null,
                'suggested_coupon' => $data['suggested_coupon'] ?? null,
                'campaign_name' => $data['campaign_name'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'max_uses' => $data['max_uses'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            CouponStatusHistory::create([
                'coupon_id' => $coupon->id,
                'from_status' => null,
                'to_status' => CouponStatus::DRAFT->value,
                'changed_by_user_id' => $actor->id,
                'note' => 'Cupom criado',
                'created_at' => now(),
            ]);

            return $coupon;
        });

        if ($autoRequest) {
            $coupon = $this->transition->request($coupon, $actor);
        }

        return $coupon->fresh(['employee', 'store', 'socialMedia', 'statusHistory']);
    }

    /**
     * Atualiza campos editáveis. Status muda via Transition Service.
     * Edição permitida em draft/requested; cupons em estados avançados
     * (issued/active) só podem ter `notes`, `campaign_name`, `valid_from`,
     * `valid_until`, `max_uses` atualizados — a menos que tenha
     * MANAGE_COUPONS.
     *
     * @throws ValidationException
     */
    public function update(Coupon $coupon, array $data, User $actor): Coupon
    {
        if ($coupon->is_deleted) {
            throw ValidationException::withMessages([
                'coupon' => 'Não é possível editar um cupom excluído.',
            ]);
        }

        // Campos que nunca vêm do request (setados internamente)
        unset(
            $data['status'],
            $data['cpf_hash'],
            $data['coupon_site'],
            $data['usage_count'],
            $data['last_used_at'],
            $data['requested_at'],
            $data['issued_at'],
            $data['activated_at'],
            $data['expired_at'],
            $data['cancelled_at'],
            $data['cancelled_reason'],
            $data['created_by_user_id'],
            $data['issued_by_user_id'],
            $data['deleted_at'],
            $data['deleted_by_user_id'],
            $data['deleted_reason'],
        );

        $earlyStates = [CouponStatus::DRAFT, CouponStatus::REQUESTED];
        $hasFullEdit = $actor->hasPermissionTo(\App\Enums\Permission::MANAGE_COUPONS->value);

        if (! in_array($coupon->status, $earlyStates, true) && ! $hasFullEdit) {
            // Em estados avançados, só campos "soft" podem ser alterados
            $allowed = ['notes', 'campaign_name', 'valid_from', 'valid_until', 'max_uses', 'social_media_link'];
            $data = array_intersect_key($data, array_flip($allowed));
        }

        // Se o tipo está sendo alterado (só em estados iniciais) ou dados
        // mudando, revalidar regras condicionais.
        if (in_array($coupon->status, $earlyStates, true)) {
            $type = isset($data['type'])
                ? CouponType::from($data['type'])
                : $coupon->type;

            // Se alterou tipo ou CPF ou store, revalidar tudo
            $relevantChange = isset($data['type'])
                || isset($data['cpf'])
                || isset($data['store_code']);

            if ($relevantChange) {
                $merged = array_merge($coupon->toArray(), $data);
                // Garante que 'cpf' venha do request quando alterado (accessor na Model
                // retorna decriptado — se não informado, usamos o decriptado atual).
                if (! isset($data['cpf'])) {
                    $merged['cpf'] = $coupon->cpf;
                }
                $this->validateTypeRules($type, $merged);

                $newCpfHash = Coupon::hashCpf($merged['cpf']);
                $newStoreCode = $type->requiresStoreAndEmployee()
                    ? ($merged['store_code'] ?? null)
                    : null;

                $this->ensureUnique($newCpfHash, $type, $newStoreCode, excludeCouponId: $coupon->id);
            }
        }

        $coupon->fill($data);
        $coupon->updated_by_user_id = $actor->id;
        $coupon->save();

        return $coupon->fresh(['employee', 'store', 'socialMedia', 'statusHistory']);
    }

    /**
     * Soft delete com motivo obrigatório. Bloqueia cupons já emitidos
     * (para emitidos, usar Cancel).
     *
     * @throws ValidationException
     */
    public function softDelete(Coupon $coupon, User $actor, string $reason): Coupon
    {
        if ($coupon->is_deleted) {
            throw ValidationException::withMessages([
                'coupon' => 'Cupom já foi excluído.',
            ]);
        }

        // Cupons já emitidos não podem ser excluídos — precisam ser cancelados
        // (motivo: preservar auditoria do código publicado externamente).
        if (in_array($coupon->status, [CouponStatus::ISSUED, CouponStatus::ACTIVE], true)) {
            throw ValidationException::withMessages([
                'coupon' => 'Cupons já emitidos não podem ser excluídos — use "Cancelar" para desativar.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'deleted_reason' => 'É obrigatório informar o motivo da exclusão.',
            ]);
        }

        $coupon->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
        ]);

        return $coupon->fresh();
    }

    /**
     * Valida os campos obrigatórios conforme o tipo do cupom:
     *  - CONSULTOR: store_code + employee_id
     *  - INFLUENCER: influencer_name + city + social_media_id
     *  - MS_INDICA: store_code + employee_id + store.network_id IN [6,7]
     *
     * @throws ValidationException
     */
    public function validateTypeRules(CouponType $type, array $data): void
    {
        $errors = [];

        if ($type->requiresStoreAndEmployee()) {
            if (empty($data['store_code'])) {
                $errors['store_code'] = 'Loja é obrigatória para cupom de '.$type->label().'.';
            }
            if (empty($data['employee_id'])) {
                $errors['employee_id'] = 'Colaborador é obrigatório para cupom de '.$type->label().'.';
            }
        }

        if ($type->requiresInfluencerFields()) {
            if (empty($data['influencer_name'])) {
                $errors['influencer_name'] = 'Nome do influencer é obrigatório.';
            }
            if (empty($data['city'])) {
                $errors['city'] = 'Cidade é obrigatória para influencer.';
            }
            if (empty($data['social_media_id'])) {
                $errors['social_media_id'] = 'Rede social é obrigatória para influencer.';
            } elseif (! empty($data['social_media_link'])) {
                // Validação contextual do link: YouTube/Facebook exigem URL,
                // Instagram/TikTok/X aceitam @usuario ou URL.
                $sm = \App\Models\SocialMedia::find($data['social_media_id']);
                if ($sm && ! $sm->validateLink($data['social_media_link'])) {
                    $errors['social_media_link'] = $sm->link_type === 'username'
                        ? "Informe @usuário ou URL válida do {$sm->name}."
                        : "Informe uma URL válida do {$sm->name} (começando com https://).";
                }
            }
        }

        if (empty($data['cpf'])) {
            $errors['cpf'] = 'CPF é obrigatório.';
        }

        // MS Indica exige loja administrativa (network_id IN [6, 7])
        if (
            $type === CouponType::MS_INDICA
            && ! empty($data['store_code'])
            && ! $this->lookup->isAdministrativeStore($data['store_code'])
        ) {
            $errors['store_code'] = 'MS Indica é restrito a lojas administrativas (E-Commerce, Qualidade, CD, Escritório).';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Valida unicidade dependente do tipo:
     *  - Consultor/MsIndica: (cpf_hash, type, store_code) entre ativos
     *    (permite colaborador ter cupons em lojas diferentes).
     *  - Influencer: (cpf_hash, type) entre ativos (sem loja).
     *
     * Considera ativos: todos os status que NÃO sejam expired/cancelled
     * + não-deletados.
     *
     * @throws ValidationException
     */
    public function ensureUnique(
        string $cpfHash,
        CouponType $type,
        ?string $storeCode = null,
        ?int $excludeCouponId = null
    ): void {
        $query = Coupon::query()
            ->where('cpf_hash', $cpfHash)
            ->where('type', $type->value)
            ->active()
            ->notDeleted();

        if ($excludeCouponId) {
            $query->where('id', '!=', $excludeCouponId);
        }

        if ($type->requiresStoreAndEmployee() && $storeCode) {
            $query->where('store_code', $storeCode);
        }

        if ($query->exists()) {
            $message = $type->requiresStoreAndEmployee() && $storeCode
                ? "Já existe cupom ativo para este CPF na loja {$storeCode} como {$type->label()}. Cancele o anterior ou use outra loja."
                : "Já existe cupom ativo para este CPF como {$type->label()}. Cancele o anterior antes de criar outro.";

            throw ValidationException::withMessages([
                'cpf' => $message,
            ]);
        }
    }
}
