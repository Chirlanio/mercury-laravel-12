<?php

namespace Tests\Unit;

use App\Services\AI\PiiSanitizer;
use App\Services\AI\SanitizedContext;
use App\Services\AI\TicketClassification;
use Tests\TestCase;

class TicketClassificationTest extends TestCase
{
    public function test_sanitized_context_strips_pii_on_build(): void
    {
        $sanitizer = new PiiSanitizer();

        $ctx = SanitizedContext::build(
            sanitizer: $sanitizer,
            rawTitle: 'Preciso do holerite — CPF 529.982.247-25',
            rawDescription: 'Meu email maria@grupo.com e telefone (85) 98746-0451',
            departmentName: 'DP',
            categories: [['id' => 1, 'name' => 'Folha de Pagamento']],
            employeeFirstName: 'Maria',
            storeCode: 'Z421',
        );

        $this->assertStringNotContainsString('529', $ctx->title);
        $this->assertStringContainsString('[cpf]', $ctx->title);
        $this->assertStringNotContainsString('maria@grupo.com', $ctx->description);
        $this->assertStringContainsString('[email]', $ctx->description);
        $this->assertStringNotContainsString('98746', $ctx->description);
        $this->assertStringContainsString('[telefone]', $ctx->description);

        // Non-sensitive fields pass through unchanged
        $this->assertSame('Maria', $ctx->employeeFirstName);
        $this->assertSame('Z421', $ctx->storeCode);
        $this->assertSame('DP', $ctx->departmentName);
    }

    public function test_sanitized_context_is_immutable_once_built(): void
    {
        $ctx = SanitizedContext::build(
            sanitizer: new PiiSanitizer(),
            rawTitle: 'Teste',
            rawDescription: 'Descrição',
            departmentName: 'TI',
            categories: [['id' => 1, 'name' => 'Hardware']],
        );

        // Readonly properties can't be reassigned
        $this->expectException(\Error::class);
        $ctx->title = 'outro';
    }

    public function test_ticket_classification_empty_helper(): void
    {
        $empty = TicketClassification::empty('test-model');

        $this->assertNull($empty->categoryId);
        $this->assertNull($empty->priority);
        $this->assertSame(0.0, $empty->confidence);
        $this->assertSame('test-model', $empty->model);
        $this->assertTrue($empty->isEmpty());
    }

    public function test_ticket_classification_populated_is_not_empty(): void
    {
        $classification = new TicketClassification(
            categoryId: 5,
            priority: 3,
            confidence: 0.85,
            model: 'llama-3.3-70b',
            summary: 'Resumo',
        );

        $this->assertFalse($classification->isEmpty());
        $this->assertSame(5, $classification->categoryId);
        $this->assertSame(0.85, $classification->confidence);
    }
}
