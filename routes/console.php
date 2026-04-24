<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Movements sync: incremental every 5 minutes, full daily at 06:00
Schedule::command('movements:sync today')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/movements-sync.log'));

Schedule::command('movements:sync auto')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/movements-sync.log'));

// Store Goals mid-month alert: runs on the 15th of each month at 9:00 AM
Schedule::command('store-goals:midmonth-alert')
    ->monthlyOn(15, '09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/midmonth-alert.log'));

// Experience Tracker notifications: daily at 08:00
Schedule::command('experience:notify')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/experience-notifications.log'));

// Helpdesk SLA monitoring: every 10 minutes (warnings 2h before breach + breach notifications)
Schedule::command('helpdesk:sla-monitor')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/helpdesk-sla.log'));

// Helpdesk email intake (IMAP poll): every minute.
// Uses ->withoutOverlapping() so a slow fetch doesn't queue up; the
// command finishes in a few seconds for typical mailbox sizes.
Schedule::command('helpdesk:imap-fetch')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/helpdesk-imap.log'));

// Purchase Orders — CIGAM matcher: every 15 min, depois do movements:sync.
// Idempotente; pula movements já vinculados a receipts existentes.
Schedule::command('purchase-orders:cigam-match')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/purchase-orders-cigam.log'));

// Purchase Orders — alerta de ordens atrasadas: dailyAt 09:00.
Schedule::command('purchase-orders:late-alert')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/purchase-orders-late.log'));

// Purchase Orders — reconcilia ordens com recebimento total mas status
// ainda em invoiced/partial_invoiced. Rede de segurança para o matcher
// CIGAM: idempotente. hourly é suficiente — é apenas catch-up.
Schedule::command('purchase-orders:reconcile-delivered')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/purchase-orders-reconcile.log'));

// Reversals — marca estornos executados como sincronizados com o CIGAM.
// Idempotente: pula registros com synced_to_cigam_at já preenchido.
// Mesmo slot do matcher CIGAM (every 15 min) para consistência.
Schedule::command('reversals:cigam-push')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/reversals-cigam.log'));

// Reversals — alerta diário de estornos aguardando autorização há mais
// de 3 dias. Consolidado por aprovador/loja para evitar flood.
Schedule::command('reversals:stale-alert')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/reversals-stale.log'));

// Returns — alerta diário de devoluções em awaiting_product (aguardando
// o cliente postar o produto) há mais de 7 dias. Consolidado por
// processador/loja para evitar flood.
Schedule::command('returns:stale-alert')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/returns-stale.log'));

// Budgets — alerta diário de consumo ≥ 70% (warning) ou ≥ 100% (exceeded)
// para orçamentos ativos do ano corrente. Chega antes das decisões de
// compra do dia.
Schedule::command('budgets:alert')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/budgets-alert.log'));

// Coupons — ativa cupons em issued cujo valid_from já chegou (ou sem
// valid_from). Roda antes do expire-stale para que cupons recém-ativados
// possam ser expirados no mesmo ciclo se valid_until também já passou.
// Resolve o caso "e-commerce esqueceu de ativar manualmente".
Schedule::command('coupons:activate-due')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/coupons-activate.log'));

// Coupons — marca cupons com valid_until vencido como expirados.
// Roda antes do expediente para que a listagem do dia já reflita o estado.
Schedule::command('coupons:expire-stale')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/coupons-expire.log'));

// Coupons — lembrete diário de cupons em requested há mais de 3 dias
// sem emissão de código pela equipe e-commerce.
Schedule::command('coupons:remind-pending')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/coupons-remind.log'));

// DRE rebuild — reconciliação defensiva semanal (domingo 03:00).
// Prompt 8 do playbook: caso observer tenha falhado silenciosamente em
// algum saving ao longo da semana, o rebuild traz o estado de volta ao
// canônico. Só reprojeta OrderPayment (fonte principal); Sale tende a ser
// mais estável, mas pode ser incluído trocando para --source=all.
Schedule::command('dre:rebuild-actuals --source=ORDER_PAYMENT --force')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/dre-rebuild.log'));

// DRE warm-up — aquece cache da matriz (mês corrente + 12 meses móveis)
// todo dia 05:50, 10 minutos antes do sync CIGAM das 06:00. Garante que o
// time financeiro abra a tela com cache hit em vez de pagar a query pesada.
Schedule::command('dre:warm-cache')
    ->dailyAt('05:50')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/dre-warm.log'));

// Consignments — marca como overdue as consignações com prazo vencido.
// Roda antes do expediente para que a listagem do dia já reflita
// corretamente os estados. Listener envia notificações ao criador
// + gerentes MANAGE_CONSIGNMENTS.
Schedule::command('consignments:mark-overdue')
    ->dailyAt('06:15')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/consignments-overdue.log'));

// Consignments — lembrete diário para consultor/criador de consignações
// com prazo a vencer nos próximos 2 dias. Chega no início do expediente
// para ação preventiva antes de virar overdue.
Schedule::command('consignments:remind-upcoming --days=2')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/consignments-remind.log'));

// Consignments — alerta crítico para supervisão (MANAGE_CONSIGNMENTS)
// de consignações em overdue há 7+ dias. Rodado logo após o reminder
// com destinatários e mensagem distintos.
Schedule::command('consignments:overdue-alert --days=7')
    ->dailyAt('09:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/consignments-alert.log'));

// Consignments — reconcilia retornos registrados manualmente com os
// movements do CIGAM (code=21) após cada sync. Every 15min — mesmo
// ritmo do movements:sync. Idempotente (whereNull movement_id).
Schedule::command('consignments:cigam-match')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/consignments-cigam-match.log'));

// Customers — sincroniza view CIGAM msl_dcliente_. Roda dailyAt 04:00
// (antes do movements:sync das 06:00) porque a base de clientes raramente
// muda de hora em hora — diário é suficiente. Idempotente (upsert por
// cigam_code). Pula tenants sem o módulo instalado.
Schedule::command('customers:sync')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/customers-sync.log'));

// Customers VIP — atualiza sugestões automáticas do ano corrente. Curadorias
// manuais ficam sempre preservadas (service só toca final_tier quando não
// tem curated_at). Weekly porque o ranking muda pouco de dia para dia;
// Marketing pode rodar sob demanda no botão da UI quando precisar.
Schedule::command('customers:vip-suggest')
    ->weeklyOn(1, '05:30') // Segunda-feira 05:30
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/customers-vip-suggest.log'));
