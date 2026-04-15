<?php

namespace App\Services;

/**
 * Converte strings de prazo de pagamento do varejo BR ("30/60/90/120") em
 * arrays de dias estruturados.
 *
 * Formatos aceitos:
 *  - "30/60/90"            → [30, 60, 90]
 *  - "30 60 90"            → [30, 60, 90]
 *  - "30,60,90"            → [30, 60, 90]
 *  - "À vista"             → [0]
 *  - "à vista" / "AVISTA"  → [0]
 *  - "30d/60d"             → [30, 60]
 *  - "30 dias"             → [30]
 *  - "" / null             → []
 *
 * Não aceita formatos exóticos como "1+30/60" ou "30 60d" mistos. Devolve
 * array vazio quando não consegue parsear nada.
 */
class PaymentTermsParser
{
    /**
     * @return array<int, int> Lista de dias até cada parcela (cada elemento >= 0).
     */
    public function parse(?string $terms): array
    {
        if ($terms === null) {
            return [];
        }

        $normalized = trim($terms);
        if ($normalized === '') {
            return [];
        }

        // Casos especiais de pagamento à vista
        $lower = mb_strtolower($normalized);
        if (in_array($lower, ['à vista', 'a vista', 'avista', 'à-vista', 'a-vista'], true)) {
            return [0];
        }

        // Remove sufixos comuns "dias", "d" e separadores
        $normalized = preg_replace('/\bdias?\b/i', '', $normalized);
        $normalized = preg_replace('/(?<=\d)d\b/i', '', $normalized);

        // Split por barra, vírgula, espaço ou ponto-e-vírgula
        $parts = preg_split('/[\/,;\s]+/', $normalized) ?: [];

        $days = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (! ctype_digit($part)) {
                continue;
            }
            $value = (int) $part;
            if ($value < 0 || $value > 9999) {
                continue;
            }
            $days[] = $value;
        }

        return $days;
    }

    /**
     * Calcula valor de cada parcela dado um total. Distribui igualmente,
     * com a última parcela absorvendo o resto pra evitar centavos perdidos.
     *
     * @return array<int, float>
     */
    public function splitAmount(float $total, int $count): array
    {
        if ($count <= 0 || $total <= 0) {
            return [];
        }

        $base = floor(($total / $count) * 100) / 100;
        $accumulated = $base * ($count - 1);
        $last = round($total - $accumulated, 2);

        $result = array_fill(0, $count - 1, $base);
        $result[] = $last;

        return $result;
    }
}
