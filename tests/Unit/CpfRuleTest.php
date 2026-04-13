<?php

namespace Tests\Unit;

use App\Rules\Cpf;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CpfRuleTest extends TestCase
{
    public function test_valid_cpf_passes_static_helper(): void
    {
        $this->assertTrue(Cpf::isValid('52998224725'));
        $this->assertTrue(Cpf::isValid('529.982.247-25'));
    }

    public function test_invalid_checksum_fails(): void
    {
        $this->assertFalse(Cpf::isValid('52998224726')); // last digit wrong
        $this->assertFalse(Cpf::isValid('12345678900'));
    }

    public function test_wrong_length_fails(): void
    {
        $this->assertFalse(Cpf::isValid('123'));
        $this->assertFalse(Cpf::isValid('123456789012')); // 12 digits
    }

    public function test_blacklisted_sequence_fails(): void
    {
        // All the same digit — technically pass checksum but rejected by law.
        foreach (range(0, 9) as $d) {
            $this->assertFalse(Cpf::isValid(str_repeat((string) $d, 11)), "Rejects {$d}x11");
        }
    }

    public function test_rule_can_be_used_with_laravel_validator(): void
    {
        $validator = Validator::make(
            ['cpf' => '52998224725'],
            ['cpf' => ['required', new Cpf()]],
        );
        $this->assertTrue($validator->passes());

        $validator = Validator::make(
            ['cpf' => '11111111111'],
            ['cpf' => ['required', new Cpf()]],
        );
        $this->assertTrue($validator->fails());
    }

    public function test_normalize_strips_formatting(): void
    {
        $this->assertSame('52998224725', Cpf::normalize('529.982.247-25'));
        $this->assertSame('52998224725', Cpf::normalize(' 529 982 247 25 '));
        $this->assertSame('52998224725', Cpf::normalize('52998224725'));
    }
}
