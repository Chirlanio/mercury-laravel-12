<?php

namespace App\Services\Customers;

/**
 * Utilitários estáticos de normalização de campos vindos do CIGAM.
 *
 * Princípios:
 *  - Retorna null para entradas vazias (whitespace) em vez de string vazia
 *  - Nunca throws — entrada maluca → null + documentação do comportamento
 *  - Idempotente — chamar 2x produz o mesmo resultado
 */
class CustomerSanitizer
{
    /**
     * Nome completo: trim + collapse whitespace + uppercase + limita a 200.
     * Remove caracteres de controle e preserva acentos.
     */
    public static function normalizeName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Collapse primeiro (converte tab/newline em espaço), depois
        // remove control-chars remanescentes que não eram whitespace.
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr(mb_strtoupper($value, 'UTF-8'), 0, 200);
    }

    /**
     * CPF (11 dígitos) ou CNPJ (14 dígitos). Retorna só dígitos.
     * Strings com menos de 11 ou fora de [11, 14] → null (CIGAM às vezes
     * tem cadastros truncados ou com lixo).
     */
    public static function normalizeCpf(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '' || ! in_array(strlen($digits), [11, 14], true)) {
            return null;
        }

        return $digits;
    }

    /**
     * Telefone/celular — concatena DDD + número. Aceita tamanhos
     * comuns (10 dígitos fixo, 11 dígitos celular, 12 DDI+DDD+fixo).
     * Remove zeros esquerdos espúrios e valida tamanho final.
     */
    public static function normalizePhone(?string $ddd, ?string $number): ?string
    {
        $dddDigits = preg_replace('/\D/', '', (string) $ddd);
        $numberDigits = preg_replace('/\D/', '', (string) $number);

        $combined = ltrim($dddDigits.$numberDigits, '0');

        if ($combined === '') {
            return null;
        }

        $len = strlen($combined);

        // DDD (2) + fixo (8) = 10; DDD (2) + celular (9) = 11
        if (! in_array($len, [10, 11], true)) {
            // Telefone inválido (truncado ou com lixo) — descarta
            return null;
        }

        return $combined;
    }

    /**
     * E-mail — trim + lowercase + valida formato. E-mail inválido → null.
     */
    public static function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(mb_strtolower($value, 'UTF-8'));
        if ($value === '') {
            return null;
        }

        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return mb_substr($value, 0, 255);
    }

    /**
     * CEP — 8 dígitos. Aceita formato com hífen ou só dígitos.
     */
    public static function normalizeZipcode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 8) {
            return null;
        }

        return $digits;
    }

    /**
     * UF — 2 letras uppercase. Inválido → null.
     */
    public static function normalizeState(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));
        if (! preg_match('/^[A-Z]{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Campo de texto simples — trim + uppercase + limita tamanho.
     * Usado para bairro/cidade/endereço/complemento.
     */
    public static function normalizeText(?string $value, int $maxLength = 250): ?string
    {
        if ($value === null) {
            return null;
        }

        // Collapse primeiro (converte tab/newline em espaço), depois
        // remove control-chars remanescentes que não eram whitespace.
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr(mb_strtoupper($value, 'UTF-8'), 0, $maxLength);
    }

    /**
     * Tipo de pessoa F (física) ou J (jurídica). Qualquer outro → null.
     */
    public static function normalizePersonType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return in_array($value, ['F', 'J'], true) ? $value : null;
    }

    /**
     * Sexo M/F. Aceita algumas variantes comuns (M/F, Masculino/Feminino, 1/2).
     */
    public static function normalizeGender(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return match ($value) {
            'M', 'MASCULINO', '1' => 'M',
            'F', 'FEMININO', '2' => 'F',
            default => null,
        };
    }

    /**
     * Data — aceita Y-m-d, d/m/Y, Y-m-d H:i:s. Inválido ou data < 1900 → null.
     *
     * Observação: PHP nativamente interpreta "23/04/2026" como m/d/Y
     * (americano) e retorna inválido. Parse explícito do formato BR
     * antes de cair no constructor padrão.
     */
    public static function normalizeDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            $dt = \DateTimeImmutable::createFromInterface($value);
        } else {
            $str = trim((string) $value);
            if ($str === '') {
                return null;
            }

            // Detecta formato brasileiro d/m/Y (com ou sem hora)
            $brDate = \DateTimeImmutable::createFromFormat('d/m/Y', substr($str, 0, 10));
            if ($brDate !== false && $brDate->format('d/m/Y') === substr($str, 0, 10)) {
                $dt = $brDate;
            } else {
                try {
                    $dt = new \DateTimeImmutable($str);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        $year = (int) $dt->format('Y');
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        return $dt->format('Y-m-d');
    }
}
