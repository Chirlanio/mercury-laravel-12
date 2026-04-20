<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TaneiaConversation;
use App\Models\TaneiaMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TaneiaAugmentTrainingCommand extends Command
{
    protected $signature = 'taneia:augment-training
        {--tenant= : ID do tenant (obrigatorio em contexto central; dispensavel via tenants:run)}
        {--variations=3 : Numero de variacoes geradas por mensagem de usuario}
        {--out= : Caminho do arquivo .jsonl (default storage/app/taneia-training/augmented-*)}
        {--all : Ignora rating e augmenta todas as conversas}
        {--min-turns=1 : Numero minimo de pares user-assistant por conversa}
        {--limit=0 : Maximo de conversas a processar, 0 = sem limite}
        {--dry-run : Nao chama a API — mostra quantos exemplos seriam gerados}';

    protected $description = 'Gera variacoes (paraphrase) das mensagens de usuario via Groq para aumentar o dataset de fine-tuning';

    private const SYSTEM_PROMPT = (
        'Voce e a TaneIA, assistente virtual do Grupo Meia Sola. '
        .'Responda sempre em portugues brasileiro, com clareza e cordialidade. '
        .'Quando nao souber a resposta, admita com honestidade.'
    );

    public function handle(): int
    {
        if (! tenant() && $this->option('tenant')) {
            $tenant = Tenant::find($this->option('tenant'));
            if (! $tenant) {
                $this->error("Tenant '{$this->option('tenant')}' nao encontrado.");

                return self::FAILURE;
            }
            tenancy()->initialize($tenant);
        }

        if (! tenant()) {
            $this->error('Contexto tenant nao inicializado. Use --tenant=ID ou rode via tenants:run.');

            return self::FAILURE;
        }

        $apiKey = config('helpdesk.ai.groq.api_key');
        $dryRun = (bool) $this->option('dry-run');

        if (! $apiKey && ! $dryRun) {
            $this->error('GROQ_API_KEY nao configurada. Defina no .env ou rode com --dry-run.');

            return self::FAILURE;
        }

        $tenantId = tenant()->id;
        $filterByRating = ! $this->option('all');
        $minTurns = (int) $this->option('min-turns');
        $limit = (int) $this->option('limit');
        $variations = max(1, (int) $this->option('variations'));

        $outPath = $this->option('out') ?: sprintf(
            'taneia-training/augmented-%s-%s.jsonl',
            $tenantId,
            now()->format('Ymd-His')
        );

        $conversations = $this->loadConversations($filterByRating, $limit);

        $this->info("Tenant: {$tenantId}");
        $this->info('Filtro rating: '.($filterByRating ? 'rating=1' : 'NAO (todas)'));
        $this->info("Variacoes por par: {$variations}");
        $this->info("Conversas candidatas: {$conversations->count()}");
        $this->info('Dry run: '.($dryRun ? 'SIM' : 'NAO'));
        $this->newLine();

        $pairs = $this->extractPairs($conversations, $filterByRating, $minTurns);

        if (empty($pairs)) {
            $this->warn('Nenhum par user/assistant elegivel. Nada a fazer.');

            return self::SUCCESS;
        }

        $originalCount = count($pairs);
        $this->info("Pares originais: {$originalCount}");
        $this->info('Exemplos estimados (originais + variacoes): '.($originalCount * ($variations + 1)));

        if ($dryRun) {
            $this->newLine();
            $this->line('Dry run ativado — nenhuma chamada a API foi feita.');

            return self::SUCCESS;
        }

        $lines = [];
        $bar = $this->output->createProgressBar($originalCount);
        $bar->start();
        $generated = 0;
        $failed = 0;
        $abort = false;

        foreach ($pairs as $pair) {
            $lines[] = $this->toJsonl($pair['user'], $pair['assistant']);

            if ($abort) {
                $failed++;
                $bar->advance();

                continue;
            }

            $result = $this->paraphrase($pair['user'], $variations, $apiKey);
            if ($result === null) {
                $failed++;
                $bar->advance();

                continue;
            }
            if ($result === 'fatal') {
                $failed++;
                $abort = true;
                $bar->advance();

                continue;
            }

            foreach ($result as $variant) {
                $lines[] = $this->toJsonl($variant, $pair['assistant']);
                $generated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        Storage::disk('local')->put($outPath, implode("\n", $lines)."\n");
        $absolutePath = Storage::disk('local')->path($outPath);

        $this->info("Originais mantidos: {$originalCount}");
        $this->info("Variacoes geradas: {$generated}");
        if ($failed > 0) {
            $this->warn("Pares sem variacao (falha na API): {$failed}");
        }
        $this->info('Total de exemplos: '.count($lines));
        $this->info("Arquivo:  {$absolutePath}");

        return self::SUCCESS;
    }

    private function loadConversations(bool $filterByRating, int $limit)
    {
        $query = TaneiaConversation::query()->with([
            'messages' => fn ($q) => $q->orderBy('id'),
        ]);

        if ($filterByRating) {
            $query->whereHas('messages', fn ($q) => $q->where('role', TaneiaMessage::ROLE_ASSISTANT)->where('rating', 1))
                ->whereDoesntHave('messages', fn ($q) => $q->where('role', TaneiaMessage::ROLE_ASSISTANT)->where('rating', -1));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Extrai pares (user, assistant) independentes de todas as conversas.
     * Cada par vira um exemplo single-turn durante a augmentacao — isso
     * evita que o paraphrase de uma pergunta inicial quebre o contexto
     * das respostas subsequentes.
     *
     * @return array<int, array{user: string, assistant: string}>
     */
    private function extractPairs($conversations, bool $filterByRating, int $minTurns): array
    {
        $pairs = [];

        foreach ($conversations as $conversation) {
            $conversationPairs = [];
            $pending = null;

            foreach ($conversation->messages as $msg) {
                if ($msg->role === TaneiaMessage::ROLE_USER) {
                    $pending = $msg;

                    continue;
                }

                if ($msg->role !== TaneiaMessage::ROLE_ASSISTANT || $pending === null) {
                    continue;
                }

                if ($filterByRating && $msg->rating !== 1) {
                    $pending = null;

                    continue;
                }

                $conversationPairs[] = [
                    'user' => trim((string) $pending->content),
                    'assistant' => trim((string) $msg->content),
                ];
                $pending = null;
            }

            if (count($conversationPairs) >= $minTurns) {
                array_push($pairs, ...$conversationPairs);
            }
        }

        return $pairs;
    }

    /**
     * Pede ao Groq N paraphrases da mensagem do usuario.
     *
     * Retornos:
     *   array<string> — variacoes geradas
     *   null          — falha transitoria (timeout, rate limit parcial); continua o loop
     *   'fatal'       — erro irrecuperavel (401 invalid key); o chamador aborta as chamadas restantes
     *
     * @return array<int, string>|string|null
     */
    private function paraphrase(string $userMessage, int $n, string $apiKey): array|string|null
    {
        $prompt = <<<PROMPT
Voce recebera uma mensagem de um colaborador do Grupo Meia Sola enviada para a assistente virtual TaneIA. Gere exatamente {$n} reformulacoes diferentes da mesma mensagem, preservando a intencao e o contexto. Varie o estilo (formal/informal), a ordem das informacoes, o nivel de detalhe e inclua erros leves de digitacao em algumas variantes (pt-BR). Mantenha o mesmo sentido — nao adicione nem remova informacoes essenciais.

Mensagem original:
"""
{$userMessage}
"""

Responda SOMENTE em JSON valido no formato:
{"variations": ["texto1", "texto2", ...]}
PROMPT;

        try {
            $response = Http::baseUrl(rtrim((string) config('helpdesk.ai.groq.base_url'), '/'))
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->timeout(20)
                ->post('/chat/completions', [
                    'model' => config('helpdesk.ai.groq.model'),
                    'temperature' => 0.9,
                    'max_tokens' => 800,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Voce gera paraphrases em portugues brasileiro. Responda somente em JSON valido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->warn('Falha HTTP no Groq: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->newLine();
            $this->warn('Groq retornou '.$response->status().' — '.substr($response->body(), 0, 200));

            if (in_array($response->status(), [401, 403], true)) {
                $this->error('Credencial invalida. Abortando chamadas restantes — verifique GROQ_API_KEY no .env.');

                return 'fatal';
            }

            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            return null;
        }

        $data = json_decode($content, true);
        if (! is_array($data) || ! isset($data['variations']) || ! is_array($data['variations'])) {
            return null;
        }

        $variations = array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : '', $data['variations']),
            fn ($v) => $v !== '' && $v !== $userMessage
        ));

        return array_slice($variations, 0, $n);
    }

    private function toJsonl(string $user, string $assistant): string
    {
        return json_encode([
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $user],
                ['role' => 'assistant', 'content' => $assistant],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
