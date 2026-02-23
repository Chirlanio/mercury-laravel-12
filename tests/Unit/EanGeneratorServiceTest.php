<?php

namespace Tests\Unit;

use App\Services\EanGeneratorService;
use PHPUnit\Framework\TestCase;

class EanGeneratorServiceTest extends TestCase
{
    private EanGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EanGeneratorService();
    }

    public function test_generates_13_digit_code(): void
    {
        $ean = $this->service->generate(1, 1);
        $this->assertMatchesRegularExpression('/^\d{13}$/', $ean);
    }

    public function test_starts_with_prefix_2(): void
    {
        $ean = $this->service->generate(1, 1);
        $this->assertEquals('2', $ean[0]);
    }

    public function test_encodes_product_id_in_6_digits(): void
    {
        $ean = $this->service->generate(123, 1);
        $this->assertEquals('000123', substr($ean, 1, 6));
    }

    public function test_encodes_variant_id_in_5_digits(): void
    {
        $ean = $this->service->generate(1, 456);
        $this->assertEquals('00456', substr($ean, 7, 5));
    }

    public function test_pads_small_ids(): void
    {
        $ean = $this->service->generate(1, 1);
        $this->assertEquals('200000100001', substr($ean, 0, 12));
    }

    public function test_check_digit_is_valid(): void
    {
        $ean = $this->service->generate(1, 1);
        $this->assertTrue($this->service->isValid($ean));
    }

    public function test_different_ids_produce_different_codes(): void
    {
        $ean1 = $this->service->generate(1, 1);
        $ean2 = $this->service->generate(1, 2);
        $ean3 = $this->service->generate(2, 1);

        $this->assertNotEquals($ean1, $ean2);
        $this->assertNotEquals($ean1, $ean3);
    }

    public function test_same_ids_produce_same_code(): void
    {
        $ean1 = $this->service->generate(42, 99);
        $ean2 = $this->service->generate(42, 99);

        $this->assertEquals($ean1, $ean2);
    }

    public function test_is_valid_accepts_valid_ean(): void
    {
        $ean = $this->service->generate(100, 200);
        $this->assertTrue($this->service->isValid($ean));
    }

    public function test_is_valid_rejects_wrong_length(): void
    {
        $this->assertFalse($this->service->isValid('12345'));
        $this->assertFalse($this->service->isValid('12345678901234'));
    }

    public function test_is_valid_rejects_non_numeric(): void
    {
        $this->assertFalse($this->service->isValid('123456789012A'));
    }

    public function test_is_valid_rejects_wrong_check_digit(): void
    {
        $ean = $this->service->generate(1, 1);
        // Corrupt the last digit
        $lastDigit = (int) $ean[12];
        $corruptedDigit = ($lastDigit + 1) % 10;
        $corrupted = substr($ean, 0, 12) . $corruptedDigit;

        $this->assertFalse($this->service->isValid($corrupted));
    }

    public function test_calculate_check_digit_known_value(): void
    {
        // Known EAN-13: 5901234123457 → check digit = 7
        $checkDigit = $this->service->calculateCheckDigit('590123412345');
        $this->assertEquals(7, $checkDigit);
    }

    public function test_large_ids(): void
    {
        $ean = $this->service->generate(999999, 99999);
        $this->assertMatchesRegularExpression('/^\d{13}$/', $ean);
        $this->assertTrue($this->service->isValid($ean));
        $this->assertEquals('299999999999', substr($ean, 0, 12));
    }
}
