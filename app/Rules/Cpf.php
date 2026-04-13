<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a Brazilian CPF using the official check-digit algorithm.
 *
 *   use App\Rules\Cpf;
 *   $request->validate(['cpf' => ['required', new Cpf]]);
 *
 * Can also be used procedurally via the static helper:
 *
 *   Cpf::isValid('12345678909') // true/false
 *
 * The rule is format-tolerant: it strips non-digit characters before
 * checking, so both "123.456.789-09" and "12345678909" are accepted.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! static::isValid($value)) {
            $fail('O :attribute informado não é um CPF válido.');
        }
    }

    public static function isValid(string $cpf): bool
    {
        $cpf = self::normalize($cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // Reject trivially invalid CPFs like 00000000000, 11111111111, etc.
        // These pass the checksum but are blacklisted by the Receita Federal.
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip CPF to its 11 digits. Returns the raw string if it doesn't
     * look like a CPF at all — caller decides how to react.
     */
    public static function normalize(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? $cpf;
    }
}
