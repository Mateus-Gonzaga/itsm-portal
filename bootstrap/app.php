<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Erros inesperados também viram aviso por e-mail para a equipe
        // (além do log). Ver App\Support\ErrorAlert.
        $exceptions->report(function (\Throwable $e): void {
            if (app()->environment('production')) {
                \App\Support\ErrorAlert::report($e);
            }
        });
    })->create();
