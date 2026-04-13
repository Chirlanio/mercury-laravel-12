<?php

namespace App\Services\AI;

use App\Services\AI\Classifiers\GroqClassifier;
use App\Services\AI\Classifiers\NullClassifier;

/**
 * Resolves a TicketClassifierInterface implementation from
 * config('helpdesk.ai.classifier'). Centralizes provider switching so the
 * rest of the app never instantiates classifiers directly.
 *
 *   config('helpdesk.ai.classifier') = 'null'   → NullClassifier (default)
 *   config('helpdesk.ai.classifier') = 'groq'   → GroqClassifier
 *
 * Unknown drivers fall back to NullClassifier with a warning, keeping the
 * pipeline functional even if someone mistypes the env var.
 */
class ClassifierFactory
{
    public function make(): TicketClassifierInterface
    {
        $driver = (string) config('helpdesk.ai.classifier', 'null');

        return match ($driver) {
            'groq' => new GroqClassifier(
                apiKey: config('helpdesk.ai.groq.api_key'),
                baseUrl: (string) config('helpdesk.ai.groq.base_url', 'https://api.groq.com/openai/v1'),
                model: (string) config('helpdesk.ai.groq.model', 'llama-3.3-70b-versatile'),
                defaultPrompt: (string) config('helpdesk.ai.default_prompt', $this->fallbackPrompt()),
                rateLimitPerMinute: (int) config('helpdesk.ai.groq.rate_limit_per_minute', 25),
            ),
            'null' => new NullClassifier(),
            default => new NullClassifier(),
        };
    }

    /**
     * Hardcoded fallback prompt used when config('helpdesk.ai.default_prompt')
     * is missing. Kept inside the factory so we always have something sane.
     */
    protected function fallbackPrompt(): string
    {
        return <<<'PROMPT'
Você é um assistente que classifica chamados do helpdesk.

Dados do chamado:
- Departamento: {{department_name}}
- {{employee_block}}
- Título: "{{title}}"
- Descrição: "{{description}}"

Categorias disponíveis neste departamento:
{{categories_list}}

Analise o conteúdo e devolva APENAS um JSON com os campos:
{
  "category_id": <id da categoria mais adequada, do conjunto acima>,
  "priority": <1 (Baixa), 2 (Média), 3 (Alta) ou 4 (Urgente)>,
  "confidence": <número entre 0 e 1 indicando sua certeza>,
  "summary": "<uma frase curta resumindo o chamado>"
}

Regras:
- Se não tiver certeza da categoria, escolha a mais próxima e ajuste confidence para baixo.
- Prioridade 4 (Urgente) só para situações que bloqueiam o trabalho agora.
- Nunca invente category_id fora da lista; se nenhuma servir, use a categoria "Outros".
- Responda SOMENTE o JSON, sem texto antes ou depois.
PROMPT;
    }
}
