<?php

use App\Http\Middleware\VerificaAutentificarea;
use App\Http\Middleware\VerificaRolul;
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
        // Numele folosite in routes/web.php
        $middleware->alias([
            'autentificat' => VerificaAutentificarea::class,
            'rol' => VerificaRolul::class,
        ]);

        // Parolele raman exact cum au fost scrise (fara stergerea spatiilor).
        $middleware->trimStrings(except: [
            'password',
            'confirm',
            'current',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
