<?php

namespace App\Providers;

use App\Events\Helpdesk\TicketAssignedEvent;
use App\Events\Helpdesk\TicketCommentEvent;
use App\Events\Helpdesk\TicketCreatedEvent;
use App\Events\Helpdesk\TicketStatusChangedEvent;
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
        //
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
    }

    protected function registerHelpdeskListeners(): void
    {
        Event::listen(TicketCreatedEvent::class, SendTicketCreatedNotifications::class);
        Event::listen(TicketAssignedEvent::class, SendTicketAssignedNotifications::class);
        Event::listen(TicketStatusChangedEvent::class, SendTicketStatusChangedNotifications::class);
        Event::listen(TicketCommentEvent::class, SendTicketCommentNotifications::class);
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
