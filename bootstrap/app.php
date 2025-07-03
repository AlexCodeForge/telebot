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
        // Trust all proxies for Vercel deployment
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
            'telegram/bot-emulator'
        ]);

        // Replace the default TrimStrings middleware with our serverless version
        $middleware->remove(\Illuminate\Foundation\Http\Middleware\TrimStrings::class);
        $middleware->append(\App\Http\Middleware\ServerlessTrimStrings::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
