<?php

namespace Tests\Unit\Customers;

use App\Services\Customers\CustomerSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Cobertura unitária do sanitizer. Garante que entradas sujas do CIGAM
 * (whitespace, acentos, formatos variados) sempre produzem valores
 * canônicos ou null.
 */
class CustomerSanitizerTest extends TestCase
{
    // ------------------------------------------------------------------
    // normalizeName
    // ------------------------------------------------------------------

    public function test_name_uppercases_and_trims(): void
    {
        $this->assertSame('MARIA DAS DORES', CustomerSanitizer::normalizeName('  maria das dores  '));
    }

    public function test_name_collapses_whitespace(): void
    {
        $this->assertSame('JOÃO DA SILVA', CustomerSanitizer::normalizeName("João   da\tSilva"));
    }

    public function test_name_preserves_accents(): void
    {
        $this->assertSame('MARIA CEARÁ', CustomerSanitizer::normalizeName('Maria Ceará'));
    }

    public function test_name_returns_null_for_empty(): void
    {
        $this->assertNull(CustomerSanitizer::normalizeName(null));
        $this->assertNull(CustomerSanitizer::normalizeName(''));
        $this->assertNull(CustomerSanitizer::normalizeName('   '));
    }

    // ------------------------------------------------------------------
    // normalizeCpf
    // ------------------------------------------------------------------

    public function test_cpf_valid_11_digits(): void
    {
        $this->assertSame('12345678909', CustomerSanitizer::normalizeCpf('123.456.789-09'));
        $this->assertSame('12345678909', CustomerSanitizer::normalizeCpf('12345678909'));
    }

    public function test_cpf_valid_14_cnpj(): void
    {
        $this->assertSame('12345678000195', CustomerSanitizer::normalizeCpf('12.345.678/0001-95'));
    }

    public function test_cpf_invalid_length_returns_null(): void
    {
        $this->assertNull(CustomerSanitizer::normalizeCpf('123'));
        $this->assertNull(CustomerSanitizer::normalizeCpf('1234567890'));  // 10 dígitos
        $this->assertNull(CustomerSanitizer::normalizeCpf('123456789012345'));  // 15
        $this->assertNull(CustomerSanitizer::normalizeCpf(null));
        $this->assertNull(CustomerSanitizer::normalizeCpf(''));
    }

    // ------------------------------------------------------------------
    // normalizePhone
    // ------------------------------------------------------------------

    public function test_phone_combines_ddd_and_number(): void
    {
        $this->assertSame('85987654321', CustomerSanitizer::normalizePhone('85', '987654321'));
        $this->assertSame('8532123456', CustomerSanitizer::normalizePhone('(85)', '3212-3456'));
    }

    public function test_phone_strips_leading_zeros(): void
    {
        $this->assertSame('85987654321', CustomerSanitizer::normalizePhone('085', '987654321'));
    }

    public function test_phone_rejects_invalid_length(): void
    {
        $this->assertNull(CustomerSanitizer::normalizePhone('85', '123'));      // curto
        $this->assertNull(CustomerSanitizer::normalizePhone('85', '1234567890')); // 12
        $this->assertNull(CustomerSanitizer::normalizePhone(null, null));
    }

    // ------------------------------------------------------------------
    // normalizeEmail
    // ------------------------------------------------------------------

    public function test_email_lowercases_and_validates(): void
    {
        $this->assertSame('user@example.com', CustomerSanitizer::normalizeEmail('User@Example.COM'));
        $this->assertSame('user@example.com', CustomerSanitizer::normalizeEmail('  user@example.com  '));
    }

    public function test_email_invalid_returns_null(): void
    {
        $this->assertNull(CustomerSanitizer::normalizeEmail('foo'));
        $this->assertNull(CustomerSanitizer::normalizeEmail('foo@'));
        $this->assertNull(CustomerSanitizer::normalizeEmail('@bar.com'));
        $this->assertNull(CustomerSanitizer::normalizeEmail(null));
    }

    // ------------------------------------------------------------------
    // normalizeZipcode
    // ------------------------------------------------------------------

    public function test_zipcode_8_digits(): void
    {
        $this->assertSame('01310100', CustomerSanitizer::normalizeZipcode('01310-100'));
        $this->assertSame('01310100', CustomerSanitizer::normalizeZipcode('01310100'));
    }

    public function test_zipcode_wrong_length_null(): void
    {
        $this->assertNull(CustomerSanitizer::normalizeZipcode('1310-100'));
        $this->assertNull(CustomerSanitizer::normalizeZipcode(''));
    }

    // ------------------------------------------------------------------
    // normalizeState
    // ------------------------------------------------------------------

    public function test_state_uppercases_2_letters(): void
    {
        $this->assertSame('CE', CustomerSanitizer::normalizeState('ce'));
        $this->assertSame('SP', CustomerSanitizer::normalizeState(' SP '));
    }

    public function test_state_invalid_null(): void
    {
        $this->assertNull(CustomerSanitizer::normalizeState('CEA'));
        $this->assertNull(CustomerSanitizer::normalizeState('1'));
        $this->assertNull(CustomerSanitizer::normalizeState(null));
    }

    // ------------------------------------------------------------------
    // normalizePersonType + normalizeGender
    // ------------------------------------------------------------------

    public function test_person_type_accepts_f_and_j(): void
    {
        $this->assertSame('F', CustomerSanitizer::normalizePersonType('f'));
        $this->assertSame('J', CustomerSanitizer::normalizePersonType('J'));
        $this->assertNull(CustomerSanitizer::normalizePersonType('X'));
    }

    public function test_gender_accepts_variants(): void
    {
        $this->assertSame('M', CustomerSanitizer::normalizeGender('m'));
        $this->assertSame('M', CustomerSanitizer::normalizeGender('Masculino'));
        $this->assertSame('F', CustomerSanitizer::normalizeGender('Feminino'));
        $this->assertSame('F', CustomerSanitizer::normalizeGender('2'));
        $this->assertNull(CustomerSanitizer::normalizeGender('X'));
        $this->assertNull(CustomerSanitizer::normalizeGender(null));
    }

    // ------------------------------------------------------------------
    // normalizeDate
    // ------------------------------------------------------------------

    public function test_date_accepts_multiple_formats(): void
    {
        $this->assertSame('2026-04-23', CustomerSanitizer::normalizeDate('2026-04-23'));
        $this->assertSame('2026-04-23', CustomerSanitizer::normalizeDate('23/04/2026'));
        $this->assertSame('2026-04-23', CustomerSanitizer::normalizeDate('2026-04-23 10:30:00'));
    }

    public function test_date_invalid_returns_null(): void
    {
        $this->assertNull(CustomerSanitizer::normalizeDate('not-a-date'));
        $this->assertNull(CustomerSanitizer::normalizeDate('1800-01-01')); // ano < 1900
        $this->assertNull(CustomerSanitizer::normalizeDate(''));
        $this->assertNull(CustomerSanitizer::normalizeDate(null));
    }

    // ------------------------------------------------------------------
    // normalizeText
    // ------------------------------------------------------------------

    public function test_text_uppercases_and_limits(): void
    {
        $this->assertSame('CENTRO', CustomerSanitizer::normalizeText('centro'));
        $this->assertSame('ABC', CustomerSanitizer::normalizeText('abcdefg', 3));
        $this->assertNull(CustomerSanitizer::normalizeText(''));
    }
}
