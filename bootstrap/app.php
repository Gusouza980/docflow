<?php

use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Http\Middleware\EnsurePortalAuthenticated;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfPortalAuthenticated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'active.organization' => EnsureOrganizationIsActive::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'portal.auth' => EnsurePortalAuthenticated::class,
            'portal.guest' => RedirectIfPortalAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
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
    })->create();
