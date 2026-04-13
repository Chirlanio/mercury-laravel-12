<?php

namespace Tests\Unit;

use App\Services\AI\PiiSanitizer;
use Tests\TestCase;

class PiiSanitizerTest extends TestCase
{
    private PiiSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new PiiSanitizer();
    }

    public function test_redacts_cpf_formatted(): void
    {
        $out = $this->sanitizer->sanitize('Meu CPF é 529.982.247-25 e preciso do holerite');
        $this->assertStringNotContainsString('529', $out);
        $this->assertStringContainsString('[cpf]', $out);
    }

    public function test_redacts_cpf_unformatted(): void
    {
        $out = $this->sanitizer->sanitize('CPF: 52998224725');
        $this->assertStringContainsString('[cpf]', $out);
        $this->assertStringNotContainsString('52998224725', $out);
    }

    public function test_redacts_email(): void
    {
        $out = $this->sanitizer->sanitize('Mande para maria.silva@grupo.com.br');
        $this->assertStringContainsString('[email]', $out);
        $this->assertStringNotContainsString('maria.silva', $out);
    }

    public function test_redacts_brazilian_phone(): void
    {
        $out = $this->sanitizer->sanitize('Me ligue em (85) 98746-0451 por favor');
        $this->assertStringContainsString('[telefone]', $out);
        $this->assertStringNotContainsString('98746', $out);
    }

    public function test_redacts_phone_with_country_code(): void
    {
        $out = $this->sanitizer->sanitize('WhatsApp: +55 85 98746-0451');
        $this->assertStringContainsString('[telefone]', $out);
    }

    public function test_preserves_non_sensitive_text(): void
    {
        $text = 'Preciso do holerite do mês passado';
        $this->assertSame($text, $this->sanitizer->sanitize($text));
    }

    public function test_sanitize_is_idempotent(): void
    {
        $text = 'CPF 52998224725 e email a@b.com';
        $once = $this->sanitizer->sanitize($text);
        $twice = $this->sanitizer->sanitize($once);
        $this->assertSame($once, $twice);
    }

    public function test_assert_clean_throws_on_pii(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->sanitizer->assertClean('Meu CPF é 52998224725');
    }

    public function test_assert_clean_passes_on_clean_text(): void
    {
        $this->sanitizer->assertClean('Preciso abrir um chamado para o departamento de TI');
        $this->addToAssertionCount(1); // no exception = pass
    }
}
