<?php

namespace App\Enums;

/**
 * Tipo de uma conta no plano contábil (ERP).
 *
 * Origem do conceito: ERPs CIGAM/TAYLOR/ZZNET rotulam cada conta com
 * "S" (Sintética, totalizadora — não recebe lançamento) ou "A"
 * (Analítica — folha, recebe lançamento direto).
 *
 * Armazenamos em inglês (`synthetic`/`analytical`) para ficar consistente
 * com a nomenclatura de código, e expomos via `shortCode()` a sigla ERP
 * original ("S"/"A") usada pelo importador e pela UI de leitura.
 */
enum AccountType: string
{
    case SYNTHETIC = 'synthetic';
    case ANALYTICAL = 'analytical';

    /**
     * Sigla original do ERP ("S" para sintética, "A" para analítica).
     */
    public function shortCode(): string
    {
        return match ($this) {
            self::SYNTHETIC => 'S',
            self::ANALYTICAL => 'A',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SYNTHETIC => 'Sintética (totalizadora)',
            self::ANALYTICAL => 'Analítica (recebe lançamento)',
        };
    }

    /**
     * Resolve a partir da sigla do ERP ("S" ou "A"), tolerando
     * variações de caixa. Retorna null se não reconhecer.
     */
    public static function fromShortCode(?string $code): ?self
    {
        if ($code === null) {
            return null;
        }

        return match (strtoupper(trim($code))) {
            'S' => self::SYNTHETIC,
            'A' => self::ANALYTICAL,
            default => null,
        };
    }

    /**
     * Converte o flag legado `accepts_entries` (boolean do prompt #1) para
     * o novo enum. Usado no backfill da migration + como ponte até que
     * todo o código migre para usar `type`.
     */
    public static function fromAcceptsEntries(bool $acceptsEntries): self
    {
        return $acceptsEntries ? self::ANALYTICAL : self::SYNTHETIC;
    }

    public function acceptsEntries(): bool
    {
        return $this === self::ANALYTICAL;
    }
}
