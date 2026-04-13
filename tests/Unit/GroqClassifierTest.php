<?php

namespace Tests\Unit;

use App\Services\AI\Classifiers\GroqClassifier;
use App\Services\AI\PiiSanitizer;
use App\Services\AI\SanitizedContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GroqClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fresh rate limiter bucket for every test
        RateLimiter::clear('helpdesk-groq-classify');
    }

    protected function context(): SanitizedContext
    {
        return SanitizedContext::build(
            sanitizer: new PiiSanitizer(),
            rawTitle: 'Preciso do holerite',
            rawDescription: 'Solicito o holerite do mês passado por favor.',
            departmentName: 'DP',
            categories: [
                ['id' => 10, 'name' => 'Folha de Pagamento'],
                ['id' => 11, 'name' => 'Férias'],
                ['id' => 12, 'name' => 'Outros'],
            ],
            employeeFirstName: 'Maria',
            storeCode: 'Z421',
        );
    }

    protected function classifier(?string $apiKey = 'test-key'): GroqClassifier
    {
        return new GroqClassifier(
            apiKey: $apiKey,
            baseUrl: 'https://api.groq.com/openai/v1',
            model: 'llama-3.3-70b-versatile',
            defaultPrompt: 'Ticket: {{title}} / {{description}} / deptno={{department_name}} / cats={{categories_list}} / {{employee_block}}',
            rateLimitPerMinute: 25,
        );
    }

    public function test_missing_api_key_returns_empty(): void
    {
        $result = $this->classifier(apiKey: null)->classify($this->context());

        $this->assertTrue($result->isEmpty());
        $this->assertSame('llama-3.3-70b-versatile', $result->model);
    }

    public function test_happy_path_parses_well_formed_json(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category_id' => 10,
                                'priority' => 3,
                                'confidence' => 0.92,
                                'summary' => 'Pedido de holerite.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->classifier()->classify($this->context());

        $this->assertSame(10, $result->categoryId);
        $this->assertSame(3, $result->priority);
        $this->assertSame(0.92, $result->confidence);
        $this->assertSame('Pedido de holerite.', $result->summary);
    }

    public function test_hallucinated_category_id_is_discarded(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[ 'message' => ['content' => json_encode([
                    'category_id' => 999, // not in our context
                    'priority' => 2,
                    'confidence' => 0.8,
                ])]]],
            ], 200),
        ]);

        $result = $this->classifier()->classify($this->context());

        $this->assertNull($result->categoryId); // discarded
        $this->assertSame(2, $result->priority); // priority still accepted
    }

    public function test_invalid_priority_is_discarded(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[ 'message' => ['content' => json_encode([
                    'category_id' => 10,
                    'priority' => 7, // out of 1..4 range
                    'confidence' => 0.8,
                ])]]],
            ], 200),
        ]);

        $result = $this->classifier()->classify($this->context());

        $this->assertSame(10, $result->categoryId);
        $this->assertNull($result->priority);
    }

    public function test_non_json_content_returns_empty(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[ 'message' => ['content' => 'totally not json' ]]],
            ], 200),
        ]);

        $this->assertTrue($this->classifier()->classify($this->context())->isEmpty());
    }

    public function test_non_2xx_response_returns_empty(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => 'server go boom'], 503),
        ]);

        $this->assertTrue($this->classifier()->classify($this->context())->isEmpty());
    }

    public function test_http_exception_returns_empty(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Network down');
        });

        $this->assertTrue($this->classifier()->classify($this->context())->isEmpty());
    }

    public function test_rate_limit_returns_empty_without_calling_provider(): void
    {
        // Exhaust the rate limiter
        for ($i = 0; $i < 25; $i++) {
            RateLimiter::hit('helpdesk-groq-classify', 60);
        }

        Http::fake(); // would fail any actual request
        $result = $this->classifier()->classify($this->context());

        $this->assertTrue($result->isEmpty());
        Http::assertNothingSent();
    }

    public function test_prompt_placeholders_are_substituted(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[ 'message' => ['content' => json_encode([
                    'category_id' => 10, 'priority' => 2, 'confidence' => 0.5,
                ])]]],
            ], 200),
        ]);

        $this->classifier()->classify($this->context());

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            $userMessage = collect($body['messages'])->firstWhere('role', 'user')['content'];

            $this->assertStringContainsString('Preciso do holerite', $userMessage);
            $this->assertStringContainsString('DP', $userMessage);
            $this->assertStringContainsString('Folha de Pagamento', $userMessage);
            $this->assertStringContainsString('Maria', $userMessage);
            $this->assertStringContainsString('Z421', $userMessage);

            return true;
        });
    }
}
