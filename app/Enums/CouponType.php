<?php

namespace App\Enums;

/**
 * Tipo de beneficiário do cupom (paridade v1 — coluna `adms_types` de adms_coupons):
 *
 *  - consultor: colaborador de loja física solicita cupom pra cliente/amiga.
 *    Requer store_code + employee_id.
 *
 *  - influencer: parceiro digital (rede social) com público próprio.
 *    Requer city + social_media_id. Não vinculado a loja.
 *
 *  - ms_indica: colaborador de loja administrativa (escritório, CD, e-commerce,
 *    qualidade — network_id IN [6, 7]) no programa "member-get-member".
 *    Requer store_code + employee_id; service valida que a loja é administrativa.
 */
enum CouponType: string
{
    case CONSULTOR = 'consultor';
    case INFLUENCER = 'influencer';
    case MS_INDICA = 'ms_indica';

    public function label(): string
    {
        return match ($this) {
            self::CONSULTOR => 'Consultor(a)',
            self::INFLUENCER => 'Influencer',
            self::MS_INDICA => 'MS Indica',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CONSULTOR => 'info',
            self::INFLUENCER => 'purple',
            self::MS_INDICA => 'teal',
        };
    }

    /**
     * Indica se o tipo requer vínculo com loja + colaborador.
     */
    public function requiresStoreAndEmployee(): bool
    {
        return in_array($this, [self::CONSULTOR, self::MS_INDICA], true);
    }

    /**
     * Indica se o tipo requer cidade + rede social (fluxo Influencer).
     */
    public function requiresInfluencerFields(): bool
    {
        return $this === self::INFLUENCER;
    }

    /**
     * Indica se o tipo requer loja administrativa (MS Indica).
     * Lojas administrativas são aquelas com network_id IN [6, 7]
     * (E-Commerce + Operacional: Z441, Z442, Z443, Z999).
     */
    public function requiresAdministrativeStore(): bool
    {
        return $this === self::MS_INDICA;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
