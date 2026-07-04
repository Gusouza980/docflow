<?php

namespace App\Providers;

use App\Contracts\Mail\TransactionalMailer;
use App\Mail\LaravelTransactionalMailer;
use App\Mail\LogTransactionalMailer;
use App\Support\OrganizationContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class);

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
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
