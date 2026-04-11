<?php

namespace App\Jobs;

use App\Models\ExperienceEvaluation;
use App\Models\ExperienceNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendExperienceNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $evaluationId,
        public string $notificationType,
    ) {}

    public function handle(): void
    {
        $evaluation = ExperienceEvaluation::with(['employee', 'manager', 'store'])->find($this->evaluationId);

        if (! $evaluation) {
            Log::warning('SendExperienceNotificationJob: evaluation not found', ['id' => $this->evaluationId]);

            return;
        }

        // Skip if evaluation is already fully completed
        if ($evaluation->overall_status === 'completed') {
            return;
        }

        $this->notifyManager($evaluation);
        $this->notifyEmployee($evaluation);
    }

    protected function notifyManager(ExperienceEvaluation $evaluation): void
    {
        // Skip if manager already completed their part
        if ($evaluation->manager_status === ExperienceEvaluation::STATUS_COMPLETED) {
            return;
        }

        $manager = $evaluation->manager;
        if (! $manager?->email) {
            return;
        }

        // Check if already sent
        if ($this->alreadySent($evaluation->id, 'manager')) {
            return;
        }

        $subject = $this->buildSubject($evaluation, 'gestor');
        $body = $this->buildManagerBody($evaluation);

        try {
            Mail::send([], [], function ($message) use ($manager, $subject, $body) {
                $message->to($manager->email)
                    ->subject($subject)
                    ->text($body);
            });

            $this->recordNotification($evaluation->id, 'manager');

            Log::info('Experience notification sent to manager', [
                'evaluation_id' => $evaluation->id,
                'type' => $this->notificationType,
                'manager_email' => $manager->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Experience notification failed for manager', [
                'evaluation_id' => $evaluation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyEmployee(ExperienceEvaluation $evaluation): void
    {
        // Skip if employee already completed their part
        if ($evaluation->employee_status === ExperienceEvaluation::STATUS_COMPLETED) {
            return;
        }

        $employee = $evaluation->employee;
        if (! $employee?->email) {
            return;
        }

        // Check if already sent
        if ($this->alreadySent($evaluation->id, 'employee')) {
            return;
        }

        $subject = $this->buildSubject($evaluation, 'colaborador');
        $publicUrl = route('experience-tracker.public-form', $evaluation->employee_token);
        $body = $this->buildEmployeeBody($evaluation, $publicUrl);

        try {
            Mail::send([], [], function ($message) use ($employee, $subject, $body) {
                $message->to($employee->email)
                    ->subject($subject)
                    ->text($body);
            });

            $this->recordNotification($evaluation->id, 'employee');

            Log::info('Experience notification sent to employee', [
                'evaluation_id' => $evaluation->id,
                'type' => $this->notificationType,
                'employee_email' => $employee->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Experience notification failed for employee', [
                'evaluation_id' => $evaluation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function buildSubject(ExperienceEvaluation $evaluation, string $recipient): string
    {
        $milestone = $evaluation->milestone_label;
        $employeeName = $evaluation->employee?->name ?? 'Colaborador';

        return match ($this->notificationType) {
            ExperienceNotification::TYPE_CREATED => "[Mercury] Avaliação de {$milestone} - {$employeeName}",
            ExperienceNotification::TYPE_REMINDER_5D => "[Mercury] Lembrete: Avaliação de {$milestone} vence em 5 dias - {$employeeName}",
            ExperienceNotification::TYPE_REMINDER_DUE => "[Mercury] URGENTE: Avaliação de {$milestone} vence hoje - {$employeeName}",
            ExperienceNotification::TYPE_OVERDUE => "[Mercury] ATRASADA: Avaliação de {$milestone} vencida - {$employeeName}",
            default => "[Mercury] Avaliação de Experiência - {$employeeName}",
        };
    }

    protected function buildManagerBody(ExperienceEvaluation $evaluation): string
    {
        $employeeName = $evaluation->employee?->name ?? 'Colaborador';
        $milestone = $evaluation->milestone_label;
        $store = $evaluation->store?->name ?? '-';
        $deadline = $evaluation->milestone_date->format('d/m/Y');

        $intro = match ($this->notificationType) {
            ExperienceNotification::TYPE_CREATED => 'Uma nova avaliação de período de experiência foi criada para seu preenchimento.',
            ExperienceNotification::TYPE_REMINDER_5D => 'A avaliação abaixo vence em 5 dias. Por favor, preencha sua parte o mais breve possível.',
            ExperienceNotification::TYPE_REMINDER_DUE => 'A avaliação abaixo vence HOJE. É necessário preencher imediatamente.',
            ExperienceNotification::TYPE_OVERDUE => 'A avaliação abaixo está ATRASADA. Por favor, preencha urgentemente.',
            default => 'Segue informação sobre a avaliação de experiência.',
        };

        return <<<TEXT
        Olá, {$evaluation->manager?->name}

        {$intro}

        Dados da Avaliação:
        - Colaborador: {$employeeName}
        - Marco: {$milestone}
        - Loja: {$store}
        - Prazo: {$deadline}

        Acesse o Mercury para preencher a avaliação do gestor.

        --
        Mercury - Sistema de Gestão
        TEXT;
    }

    protected function buildEmployeeBody(ExperienceEvaluation $evaluation, string $publicUrl): string
    {
        $employeeName = $evaluation->employee?->name ?? 'Colaborador';
        $milestone = $evaluation->milestone_label;
        $deadline = $evaluation->milestone_date->format('d/m/Y');

        $intro = match ($this->notificationType) {
            ExperienceNotification::TYPE_CREATED => 'Você foi convidado(a) a preencher sua autoavaliação de período de experiência.',
            ExperienceNotification::TYPE_REMINDER_5D => 'Sua autoavaliação vence em 5 dias. Por favor, preencha o mais breve possível.',
            ExperienceNotification::TYPE_REMINDER_DUE => 'Sua autoavaliação vence HOJE. É necessário preencher imediatamente.',
            ExperienceNotification::TYPE_OVERDUE => 'Sua autoavaliação está ATRASADA. Por favor, preencha urgentemente.',
            default => 'Segue informação sobre sua avaliação de experiência.',
        };

        return <<<TEXT
        Olá, {$employeeName}

        {$intro}

        Dados da Avaliação:
        - Marco: {$milestone}
        - Prazo: {$deadline}

        Acesse o link abaixo para preencher sua autoavaliação:
        {$publicUrl}

        Este link é pessoal e não deve ser compartilhado.

        --
        Mercury - Sistema de Gestão
        TEXT;
    }

    protected function alreadySent(int $evaluationId, string $recipientType): bool
    {
        return ExperienceNotification::where('evaluation_id', $evaluationId)
            ->where('notification_type', $this->notificationType)
            ->where('recipient_type', $recipientType)
            ->exists();
    }

    protected function recordNotification(int $evaluationId, string $recipientType): void
    {
        ExperienceNotification::create([
            'evaluation_id' => $evaluationId,
            'notification_type' => $this->notificationType,
            'recipient_type' => $recipientType,
            'sent_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendExperienceNotificationJob failed', [
            'evaluation_id' => $this->evaluationId,
            'type' => $this->notificationType,
            'error' => $exception->getMessage(),
        ]);
    }
}
