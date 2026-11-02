<?php

use App\Http\Middleware\EnsureAdministratorPermission;
use App\Http\Middleware\EnsureFullAdministrator;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.permission' => EnsureAdministratorPermission::class,
            'admin.full' => EnsureFullAdministrator::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('admin', 'admin/*')
                ? route('admin.login')
                : route('login'),
        );
        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->is('admin/login')
                ? route('admin.dashboard')
                : route('account.dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'currentPassword',
            'password',
            'password_confirmation',
            'token',
        ]);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
