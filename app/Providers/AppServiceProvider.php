<?php

namespace App\Providers;

use App\Billing\Asaas\AsaasClient;
use App\Billing\AsaasBillingGateway;
use App\Billing\ManualBillingGateway;
use App\Contracts\Billing\BillingGateway;
use App\Contracts\Mail\TransactionalMailer;
use App\Mail\LaravelTransactionalMailer;
use App\Mail\LogTransactionalMailer;
use App\Support\OrganizationContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class);

        $this->app->singleton(AsaasClient::class);

        $this->app->singleton(BillingGateway::class, function ($app): BillingGateway {
            return match (config('docflow.billing.driver', 'manual')) {
                'asaas' => $app->make(AsaasBillingGateway::class),
                default => new ManualBillingGateway,
            };
        });

        $this->app->singleton(TransactionalMailer::class, function (): TransactionalMailer {
            if (config('mail.default') === 'log') {
                return new LogTransactionalMailer;
            }

            return new LaravelTransactionalMailer;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
