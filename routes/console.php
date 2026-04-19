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
