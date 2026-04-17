<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TaneiaConversation;
use App\Models\TaneiaMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TaneiaExportTrainingDataCommand extends Command
{
    protected $signature = 'taneia:export-training
        {--tenant= : ID do tenant (obrigatorio em contexto central; dispensavel se ja estiver no dominio do tenant)}
        {--out= : Caminho do arquivo .jsonl (default em storage/app/taneia-training/)}
        {--all : Ignora rating e exporta todas as conversas}
        {--min-turns=1 : Numero minimo de pares user-assistant por conversa}
        {--limit=0 : Maximo de conversas a exportar, 0 = sem limite}';

    protected $description = 'Exporta conversas TaneIA em JSONL para fine-tuning (formato Llama 3.1 / Unsloth)';

    /**
     * System prompt padrao injetado em cada exemplo de treino.
     * Mantido espelhado com services/taneia_service.py para consistencia.
     */
    private const SYSTEM_PROMPT = (
        'Voce e a TaneIA, assistente virtual do Grupo Meia Sola. '
        .'Responda sempre em portugues brasileiro, com clareza e cordialidade. '
        .'Quando nao souber a resposta, admita com honestidade.'
    );

    public function handle(): int
    {
        // Se rodado em contexto central, aceita --tenant= e inicializa tenancy.
        // Se ja esta em contexto tenant (via tenants:run), honra o existente.
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

        $tenantId = tenant()->id;
        $filterByRating = ! $this->option('all');
        $minTurns = (int) $this->option('min-turns');
        $limit = (int) $this->option('limit');

        $outPath = $this->option('out') ?: sprintf(
            'taneia-training/%s-%s.jsonl',
            $tenantId,
            now()->format('Ymd-His')
        );

        $conversations = $this->loadConversations($filterByRating, $limit);

        $this->info("Tenant: {$tenantId}");
        $this->info('Filtro rating positivo: '.($filterByRating ? 'SIM' : 'NAO (todas)'));
        $this->info("Conversas candidatas: {$conversations->count()}");

        $exported = 0;
        $skipped = 0;
        $lines = [];

        foreach ($conversations as $conversation) {
            $example = $this->conversationToExample($conversation, $filterByRating, $minTurns);
            if ($example === null) {
                $skipped++;

                continue;
            }
            $lines[] = json_encode($example, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $exported++;
        }

        if ($exported === 0) {
            $this->warn('Nenhuma conversa elegivel para export. Nada foi escrito.');

            return self::SUCCESS;
        }

        Storage::disk('local')->put($outPath, implode("\n", $lines)."\n");
        $absolutePath = Storage::disk('local')->path($outPath);

        $this->newLine();
        $this->info("Exportado: {$exported} exemplos");
        $this->info("Puladas:   {$skipped} conversas (sem rating/few turns)");
        $this->info("Arquivo:   {$absolutePath}");
        $this->newLine();
        $this->line('Proximo passo: siga o guia em taneia-backend/finetune/README.md para treinar via Unsloth no Colab.');

        return self::SUCCESS;
    }

    private function loadConversations(bool $filterByRating, int $limit)
    {
        $query = TaneiaConversation::query()->with([
            'messages' => fn ($q) => $q->orderBy('id'),
        ]);

        if ($filterByRating) {
            // Traz apenas conversas que tem pelo menos 1 assistente com rating=1
            // e NENHUM com rating=-1 — sinal claro de conversa "boa".
            $query->whereHas('messages', fn ($q) => $q->where('role', TaneiaMessage::ROLE_ASSISTANT)->where('rating', 1))
                ->whereDoesntHave('messages', fn ($q) => $q->where('role', TaneiaMessage::ROLE_ASSISTANT)->where('rating', -1));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Converte uma conversa no formato de treino compativel com Llama 3.1.
     *
     * Formato (OpenAI conversations / HF chat template):
     *   {"messages": [
     *     {"role": "system", "content": "..."},
     *     {"role": "user", "content": "..."},
     *     {"role": "assistant", "content": "..."}
     *   ]}
     *
     * O Unsloth aceita esse formato nativamente via `standardize_sharegpt`
     * + `apply_chat_template` usando o template do Llama 3.1.
     */
    private function conversationToExample(
        TaneiaConversation $conversation,
        bool $filterByRating,
        int $minTurns
    ): ?array {
        $turns = [];
        $pair = ['user' => null, 'assistant' => null];

        foreach ($conversation->messages as $msg) {
            if ($msg->role === TaneiaMessage::ROLE_USER) {
                // Fecha par anterior incompleto (usuario falou 2x seguidas): ignora o primeiro.
                $pair = ['user' => $msg, 'assistant' => null];

                continue;
            }

            if ($msg->role !== TaneiaMessage::ROLE_ASSISTANT) {
                continue;
            }

            if ($pair['user'] === null) {
                // Assistente sem pergunta precedente — pula.
                continue;
            }

            if ($filterByRating && $msg->rating !== 1) {
                // Neste modo, so contamos pares EXPLICITAMENTE aprovados.
                $pair = ['user' => null, 'assistant' => null];

                continue;
            }

            $turns[] = [
                'role' => 'user',
                'content' => trim((string) $pair['user']->content),
            ];
            $turns[] = [
                'role' => 'assistant',
                'content' => trim((string) $msg->content),
            ];
            $pair = ['user' => null, 'assistant' => null];
        }

        // minTurns = 1 significa "pelo menos 1 par user/assistant"
        if (count($turns) < $minTurns * 2) {
            return null;
        }

        return [
            'messages' => array_merge(
                [['role' => 'system', 'content' => self::SYSTEM_PROMPT]],
                $turns
            ),
        ];
    }
}
