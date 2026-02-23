<?php

namespace App\Services;

class EanGeneratorService
{
    /**
     * Generate an EAN-13 barcode: 2PPPPPPVVVVVC
     * P = product_id (6 digits), V = variant_id (5 digits), C = check digit
     */
    public function generate(int $productId, int $variantId): string
    {
        $prefix = '2';
        $product = str_pad($productId, 6, '0', STR_PAD_LEFT);
        $variant = str_pad($variantId, 5, '0', STR_PAD_LEFT);

        $first12 = $prefix . $product . $variant;
        $checkDigit = $this->calculateCheckDigit($first12);

        return $first12 . $checkDigit;
    }

    /**
     * Calculate the EAN-13 check digit (GS1 Mod-10)
     */
    public function calculateCheckDigit(string $first12): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $first12[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Validate an EAN-13 code (format + check digit)
     */
    public function isValid(string $ean13): bool
    {
        if (!preg_match('/^\d{13}$/', $ean13)) {
            return false;
        }

        $first12 = substr($ean13, 0, 12);
        $checkDigit = (int) $ean13[12];

        return $this->calculateCheckDigit($first12) === $checkDigit;
    }
}
