<?php

namespace App\Providers;

use App\Events\Helpdesk\HelpdeskInteractionCreated;
use App\Events\Helpdesk\TicketAssignedEvent;
use App\Events\Helpdesk\TicketCommentEvent;
use App\Events\Helpdesk\TicketCreatedEvent;
use App\Events\Helpdesk\TicketStatusChangedEvent;
use App\Events\PersonnelMovementCreated;
use App\Events\PurchaseOrderStatusChanged;
use App\Events\ReturnOrderStatusChanged;
use App\Events\ReversalStatusChanged;
use App\Listeners\CreateSubstitutionVacancyFromDismissal;
use App\Listeners\NotifyPurchaseOrderStakeholders;
use App\Listeners\NotifyReturnOrderStakeholders;
use App\Listeners\NotifyReversalStakeholders;
use App\Listeners\OpenHelpdeskTicketForReversal;
use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralPage;
use App\Observers\CentralMenuObserver;
use App\Observers\CentralMenuPageDefaultObserver;
use App\Observers\CentralPageObserver;
use App\Services\TaneiaClient;
use App\Listeners\Helpdesk\DispatchCsatSurveyListener;
use App\Listeners\Helpdesk\DispatchWhatsappReplyListener;
use App\Listeners\Helpdesk\SendTicketAssignedNotifications;
use App\Listeners\Helpdesk\SendTicketCommentNotifications;
use App\Listeners\Helpdesk\SendTicketCreatedNotifications;
use App\Listeners\Helpdesk\SendTicketStatusChangedNotifications;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TaneiaClient constructor takes scalars (url/path/timeout) that the
        // container can't autowire — bind via the static factory that reads
        // from config('services.taneia').
        $this->app->singleton(TaneiaClient::class, fn () => TaneiaClient::fromConfig());

        // DRE — leitor de períodos fechados. Concreto é o reader de snapshot
        // (prompt 11). `NullClosedPeriodReader` fica disponível no container
        // para testes que queiram desligar o overlay.
        $this->app->bind(
            \App\Services\DRE\Contracts\ClosedPeriodReader::class,
            \App\Services\DRE\DrePeriodSnapshotReader::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        // Force HTTPS in production
        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);

        // In testing, register tenant migrations so RefreshDatabase runs them
        if (app()->environment('testing')) {
            $this->loadMigrationsFrom(database_path('migrations/tenant'));
        }

        // Configure where authenticated users are redirected when hitting 'guest' middleware.
        RedirectIfAuthenticated::redirectUsing(function ($request) {
            if (Auth::guard('central')->check()) {
                return '/admin';
            }

            return '/dashboard';
        });

        $this->configureRateLimiting();
        $this->registerHelpdeskListeners();
        $this->registerHrListeners();
        $this->registerPurchaseOrderListeners();
        $this->registerReversalListeners();
        $this->registerReturnListeners();
        // Coupons: usa auto-discovery do Laravel 12 (listener em
        // App\Listeners\NotifyCouponStakeholders::handle com type-hint
        // do event — registra-se sozinho). NÃO adicionar Event::listen
        // aqui ou duplica notifications (bug ativo em Return/Reversal).
        $this->registerNavigationObservers();
    }

    protected function registerPurchaseOrderListeners(): void
    {
        // Notifica gerentes/aprovadores quando uma ordem de compra muda de
        // status. Database notification (sino do frontend) — sem mail pra
        // não inundar caixa postal.
        Event::listen(PurchaseOrderStatusChanged::class, NotifyPurchaseOrderStakeholders::class);
    }

    protected function registerReversalListeners(): void
    {
        // Notificações database (sino) em cada transição de estorno.
        Event::listen(ReversalStatusChanged::class, NotifyReversalStakeholders::class);

        // Hook opcional: abre ticket no Helpdesk Financeiro quando estorno
        // transita para pending_authorization. Fail-safe — se módulo helpdesk
        // não estiver instalado ou departamento "Financeiro" não existir,
        // apenas loga e segue.
        Event::listen(ReversalStatusChanged::class, OpenHelpdeskTicketForReversal::class);
    }

    protected function registerReturnListeners(): void
    {
        // Notificações database (sino) em cada transição de devolução.
        // Matriz de destinatários no listener (criador + aprovadores/processadores).
        Event::listen(ReturnOrderStatusChanged::class, NotifyReturnOrderStakeholders::class);
    }


    protected function registerNavigationObservers(): void
    {
        // Auto-invalidate CentralMenuResolver cache whenever navigation
        // structure changes in the SaaS admin. Without this, tenants see
        // stale sidebars for up to 5 minutes after edits.
        CentralMenu::observe(CentralMenuObserver::class);
        CentralPage::observe(CentralPageObserver::class);
        CentralMenuPageDefault::observe(CentralMenuPageDefaultObserver::class);

        // DRE — dispara AnalyticalAccountCreated em contas novas de
        // resultado (grupos 3, 4, 5). Usado pela fila de pendências.
        \App\Models\ChartOfAccount::observe(\App\Observers\ChartOfAccountObserver::class);

        // DRE — projetores automáticos para dre_actuals (prompt 8).
        \App\Models\OrderPayment::observe(\App\Observers\OrderPaymentDreObserver::class);
        \App\Models\Sale::observe(\App\Observers\SaleDreObserver::class);

        // DRE — ponte Budgets → dre_budgets quando upload fica ativo (prompt 10).
        \App\Models\BudgetUpload::observe(\App\Observers\BudgetUploadDreObserver::class);
    }

    protected function registerHelpdeskListeners(): void
    {
        Event::listen(TicketCreatedEvent::class, SendTicketCreatedNotifications::class);
        Event::listen(TicketAssignedEvent::class, SendTicketAssignedNotifications::class);
        Event::listen(TicketStatusChangedEvent::class, SendTicketStatusChangedNotifications::class);
        Event::listen(TicketCommentEvent::class, SendTicketCommentNotifications::class);
        Event::listen(HelpdeskInteractionCreated::class, DispatchWhatsappReplyListener::class);
        // CSAT pipeline: when a ticket transitions to RESOLVED, fire off
        // the satisfaction survey job (one per ticket, idempotent).
        Event::listen(TicketStatusChangedEvent::class, DispatchCsatSurveyListener::class);
    }

    protected function registerHrListeners(): void
    {
        // When a PersonnelMovement of type=dismissal with open_vacancy=true is
        // created, auto-generate a substitution vacancy in draft (status=open)
        // pre-filled with origin_movement_id and replaced_employee.
        Event::listen(PersonnelMovementCreated::class, CreateSubstitutionVacancyFromDismissal::class);
    }

    protected function configureRateLimiting(): void
    {
        // API rate limit: per tenant + IP
        RateLimiter::for('api', function (Request $request) {
            $tenant = tenant();
            $key = $tenant
                ? 'tenant:' . $tenant->id . ':' . $request->ip()
                : 'central:' . $request->ip();

            return Limit::perMinute(60)->by($key);
        });

        // Central login: prevent brute force
        RateLimiter::for('central-login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('login:' . $request->ip()),
                Limit::perMinute(10)->by('login:' . $request->input('email', '')),
            ];
        });

        // Webhook endpoints: higher limit
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(120)->by('webhook:' . $request->ip());
        });

        // General web: per tenant + user
        RateLimiter::for('web', function (Request $request) {
            $tenant = tenant();
            $userId = $request->user()?->id ?? $request->ip();
            $key = $tenant
                ? 'tenant:' . $tenant->id . ':' . $userId
                : 'central:' . $userId;

            return Limit::perMinute(120)->by($key);
        });
    }
}
