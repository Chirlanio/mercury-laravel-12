<?php

namespace App\Listeners;

use App\Events\PersonnelMovementCreated;
use App\Models\PersonnelMovement;
use App\Services\VacancyIntegrationService;
use Illuminate\Support\Facades\Log;

/**
 * Escuta PersonnelMovementCreated e, se o movimento for um desligamento
 * com a flag open_vacancy=true, cria uma vaga de substituição em rascunho
 * (status=open) via VacancyIntegrationService::suggestVacancyForDismissal.
 *
 * Síncrono por simplicidade — a criação da vaga é parte do mesmo fluxo de
 * criação do movimento e precisa estar disponível imediatamente na listagem
 * de vagas para o DP.
 */
class CreateSubstitutionVacancyFromDismissal
{
    public function __construct(
        protected VacancyIntegrationService $integrationService,
    ) {}

    public function handle(PersonnelMovementCreated $event): void
    {
        $movement = $event->movement;

        if ($movement->type !== PersonnelMovement::TYPE_DISMISSAL) {
            return;
        }

        if (! $movement->open_vacancy) {
            return;
        }

        try {
            $this->integrationService->suggestVacancyForDismissal($movement);
        } catch (\Throwable $e) {
            // Falha aqui não deve bloquear a criação do movimento. A vaga
            // pode ser criada manualmente depois pelo DP referenciando o
            // movimento. Log para diagnóstico.
            Log::warning('Failed to auto-create substitution vacancy from dismissal', [
                'movement_id' => $movement->id,
                'employee_id' => $movement->employee_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
