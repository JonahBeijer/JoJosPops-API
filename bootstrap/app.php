<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            // 1. Website Routes (jojospops.com)
            Route::middleware('web')
                ->domain(env('WEB_DOMAIN', 'jojospops.com'))
                ->group(base_path('routes/web.php'));

            // 2. API Routes (api.jojospops.com)
            Route::middleware('api')
                ->domain(env('API_DOMAIN', 'api.jojospops.com'))
                ->prefix('api') // Behoudt de /api/ prefix zodat je app code blijft werken!
                ->group(base_path('routes/api.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/stripe/webhook', // Dit moet overeenkomen met de volledige URL na het domein
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Jouw eventuele exceptions configuratie
    })->create();
