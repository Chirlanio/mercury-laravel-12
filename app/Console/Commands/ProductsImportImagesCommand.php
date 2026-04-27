<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ProductBulkImageService;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Carga em massa de imagens de produtos a partir de uma pasta no servidor.
 *
 * Uso típico — carga inicial de 70k+ imagens, fora da UI web (que tem limites
 * de memória do browser e tempo de aba). Espera arquivos nomeados como
 * "{reference}.{jpg|jpeg|png|webp}".
 *
 * Estratégia de memória: nunca acumula a lista completa de arquivos nem de
 * resultados em memória. Itera o Finder em streaming, escreve cada resultado
 * no log JSONL e mantém apenas contadores agregados.
 *
 * Exemplo:
 *   php artisan products:import-images "//srv/share/fotos" --tenant=meia-sola --skip-existing
 *   php artisan products:import-images /var/upload --tenant=demo --overwrite --dry-run
 */
class ProductsImportImagesCommand extends Command
{
    protected $signature = 'products:import-images
        {path : Caminho absoluto ou relativo da pasta com as imagens}
        {--tenant= : ID do tenant (obrigatório)}
        {--overwrite : Substituir imagens existentes (default: pula)}
        {--skip-existing : Pular produtos que já têm imagem (default)}
        {--dry-run : Não salva nada — apenas relata o que seria feito}';

    protected $description = 'Importa imagens de produtos em massa a partir de uma pasta. Identifica o produto pela referência no nome do arquivo.';

    public function __construct(private readonly ProductBulkImageService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Em CLI o limite default de 128MB do php.ini é fácil de estourar com
        // 70k+ entradas de diretório (especialmente em SMB). CLI é seguro pra
        // remover o teto.
        ini_set('memory_limit', '-1');

        $tenantId = $this->option('tenant');
        if (! $tenantId) {
            $this->error('Opção --tenant é obrigatória.');

            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' não encontrado.");

            return self::FAILURE;
        }

        $path = $this->argument('path');
        if (! is_dir($path)) {
            $this->error("Pasta '{$path}' não existe ou não é um diretório.");

            return self::FAILURE;
        }

        $onConflict = $this->option('overwrite')
            ? ProductBulkImageService::ON_CONFLICT_REPLACE
            : ProductBulkImageService::ON_CONFLICT_SKIP;

        $dryRun = (bool) $this->option('dry-run');

        $this->info("Tenant: {$tenant->id}");
        $this->info("Pasta:  {$path}");
        $this->info('Conflito: '.($onConflict === 'replace' ? 'SUBSTITUIR' : 'IGNORAR'));
        if ($dryRun) {
            $this->warn('DRY RUN — nada será salvo.');
        }

        $this->info('Contando arquivos (pode levar alguns minutos em pasta de rede)...');
        $total = $this->makeFinder($path)->count();
        if ($total === 0) {
            $this->warn('Nenhuma imagem encontrada (jpg/jpeg/png/webp).');

            return self::SUCCESS;
        }
        $this->info("Total de arquivos: {$total}");

        $counters = [
            'uploaded' => 0, 'replaced' => 0, 'skipped' => 0,
            'not_found' => 0, 'invalid' => 0, 'error' => 0,
            'would_upload' => 0, 'would_replace' => 0, 'would_skip' => 0,
        ];

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %message%');
        $bar->setMessage('Iniciando...');
        $bar->start();

        $logPath = storage_path('logs/products-import-images-'.now()->format('Ymd_His').'.log');
        $logHandle = @fopen($logPath, 'w');

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($path, $onConflict, $dryRun, $bar, $logHandle, &$counters) {
                foreach ($this->makeFinder($path) as $file) {
                    $bar->setMessage($file->getFilename());

                    if ($dryRun) {
                        $preview = $this->service->previewFilenames([$file->getFilename()]);
                        $status = match (true) {
                            count($preview['invalid']) > 0 => 'invalid',
                            count($preview['not_found']) > 0 => 'not_found',
                            count($preview['conflicts']) > 0 => $onConflict === 'replace' ? 'would_replace' : 'would_skip',
                            default => 'would_upload',
                        };
                        $row = [
                            'filename' => $file->getFilename(),
                            'status' => $status,
                            'message' => null,
                        ];
                    } else {
                        $row = $this->service->processFile(
                            file: $file->getRealPath(),
                            originalName: $file->getFilename(),
                            onConflict: $onConflict,
                            userId: null,
                        );
                    }

                    if (isset($counters[$row['status']])) {
                        $counters[$row['status']]++;
                    }

                    if ($logHandle) {
                        fwrite($logHandle, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
                    }

                    unset($row);
                    $bar->advance();
                }
            });
        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine();
            $this->error('Falha durante a importação: '.$e->getMessage());
            $exitCode = self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        if ($logHandle) {
            fclose($logHandle);
        }

        $this->printSummary($counters, $dryRun);
        $this->info("Log detalhado: {$logPath}");

        return $exitCode;
    }

    private function makeFinder(string $path): Finder
    {
        return (new Finder)
            ->files()
            ->in($path)
            ->name('/\.(jpg|jpeg|png|webp)$/i')
            ->ignoreUnreadableDirs();
    }

    /**
     * @param  array<string, int>  $counters
     */
    private function printSummary(array $counters, bool $dryRun): void
    {
        if ($dryRun) {
            $this->table(
                ['Status (dry-run)', 'Total'],
                [
                    ['Subiriam (novas)', $counters['would_upload']],
                    ['Substituiriam', $counters['would_replace']],
                    ['Pulariam (já têm)', $counters['would_skip']],
                    ['Não encontrados', $counters['not_found']],
                    ['Inválidos', $counters['invalid']],
                ],
            );

            return;
        }

        $total = $counters['uploaded'] + $counters['replaced'] + $counters['skipped']
            + $counters['not_found'] + $counters['invalid'] + $counters['error'];

        $this->table(
            ['Status', 'Total'],
            [
                ['Enviadas (novas)', $counters['uploaded']],
                ['Substituídas', $counters['replaced']],
                ['Ignoradas', $counters['skipped']],
                ['Não encontradas', $counters['not_found']],
                ['Inválidas', $counters['invalid']],
                ['Erros', $counters['error']],
                ['Total', $total],
            ],
        );

        if ($counters['error'] > 0 || $counters['not_found'] > 0 || $counters['invalid'] > 0) {
            $this->warn('Existem ocorrências — verifique o log detalhado.');
        }
    }
}
