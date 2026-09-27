<?php

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
        // Webhook dan API dipanggil mesin/skrip/bot — tanpa CSRF
        $middleware->validateCsrfTokens(except: [
            'inbound/email',
            'api/*',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias([
            'owner' => \App\Http\Middleware\EnsureOwner::class,
            'force.pwd' => \App\Http\Middleware\ForcePasswordChange::class,
            'api.token' => \App\Http\Middleware\EnsureApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // expectsJson() dipertahankan agar error validasi form AJAX tetap dijawab
        // JSON 422 (callback ini MENGGANTIKAN default Laravel, bukan menambah)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || $request->is('api/*')
                || $request->is('inbound/*'),
        );
    })->create();
