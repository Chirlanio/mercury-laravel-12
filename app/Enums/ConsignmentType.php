<?php

namespace App\Enums;

/**
 * Tipo de destinatário/contexto da consignação.
 *
 *  - cliente: pessoa física (CPF) — cliente VIP/Black leva peças para
 *    experimentar em casa. Padrão histórico da v1.
 *
 *  - influencer: parceiro de conteúdo (fotos, reels, campanhas) ou
 *    estúdio/produtora. Retorno alto, venda zero típica. Pode ser PF
 *    ou PJ; exige identificação mínima (nome + CPF/CNPJ) mas não
 *    vincula a employee_id da loja.
 *
 *  - ecommerce: loja virtual (tipicamente Z441 no CIGAM) ou centro
 *    de distribuição operado por terceiro. Volumes maiores, venda
 *    esperada. Destinatário é a própria loja/parceiro, não um CPF.
 *
 * Decisão de escopo (2026-04-23): prazo padrão unificado em 7 dias
 * para os 3 tipos — não há diferenciação de SLA por tipo. O tipo
 * segue relevante para filtros, dashboard e segregação de relatórios.
 */
enum ConsignmentType: string
{
    case CLIENTE = 'cliente';
    case INFLUENCER = 'influencer';
    case ECOMMERCE = 'ecommerce';

    public function label(): string
    {
        return match ($this) {
            self::CLIENTE => 'Cliente',
            self::INFLUENCER => 'Influencer',
            self::ECOMMERCE => 'E-commerce',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CLIENTE => 'info',
            self::INFLUENCER => 'purple',
            self::ECOMMERCE => 'teal',
        };
    }

    /**
     * Indica se o tipo exige vínculo com consultor(a) da loja.
     * E-commerce normalmente não tem consultor individual responsável.
     */
    public function requiresEmployee(): bool
    {
        return $this === self::CLIENTE;
    }

    /**
     * Indica se o destinatário pode ser pessoa jurídica (CNPJ).
     * Cliente e E-commerce aceitam CNPJ; Influencer geralmente é PF,
     * mas estúdios/produtoras também podem ser PJ.
     */
    public function allowsLegalEntity(): bool
    {
        return true;
    }

    /**
     * Prazo padrão em dias para retorno, usado no `expected_return_date`
     * quando o usuário não sobrescreve manualmente.
     */
    public function defaultReturnPeriodDays(): int
    {
        return 7;
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
