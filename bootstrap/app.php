<?php

use App\Exceptions\PlanFeatureUnavailableException;
use App\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\EnsureOrganizationAccessible;
use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsurePortalAuthenticated;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfPortalAuthenticated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'active.organization' => EnsureOrganizationIsActive::class,
            'org.accessible' => EnsureOrganizationAccessible::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'portal.auth' => EnsurePortalAuthenticated::class,
            'portal.guest' => RedirectIfPortalAuthenticated::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/billing/*',
            'webhooks/tenant/asaas/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PlanLimitExceededException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => 'plan_limit_exceeded',
                    'metric' => $exception->metric,
                    'limit' => $exception->limit,
                    'current' => $exception->current,
                ], 422);
            }

            return back()->with('error', $exception->getMessage());
        });

        $exceptions->render(function (PlanFeatureUnavailableException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => 'plan_feature_unavailable',
                    'feature' => $exception->feature,
                ], 403);
            }

            return redirect()
                ->route('organizations.plan.show')
                ->with('error', $exception->getMessage());
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('finance:generate-recurring-receivables')
            ->dailyAt('06:00')
            ->withoutOverlapping();

        $schedule->command('reports:run-schedules')
            ->dailyAt('07:00')
            ->withoutOverlapping();

        $schedule->command('finance:notify-overdue-receivables')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        $schedule->command('finance:mark-delinquent-clients')
            ->weeklyOn(1, '08:30')
            ->withoutOverlapping();

        $schedule->command('subscriptions:expire-trials')
            ->dailyAt('06:00')
            ->withoutOverlapping();

        $schedule->command('subscriptions:apply-grace-expiry')
            ->dailyAt('06:15')
            ->withoutOverlapping();

        $schedule->command('billing:generate-invoices')
            ->dailyAt('06:30')
            ->withoutOverlapping();

        $schedule->command('billing:mark-overdue-invoices')
            ->dailyAt('06:45')
            ->withoutOverlapping();

        $schedule->command('billing:notify-trial-ending')
            ->dailyAt('09:00')
            ->withoutOverlapping();

        $schedule->command('automations:dispatch-due')
            ->dailyAt('07:30')
            ->withoutOverlapping();

        $schedule->command('documents:generate-monthly-packages')
            ->dailyAt('07:45')
            ->withoutOverlapping();
    })->create();
