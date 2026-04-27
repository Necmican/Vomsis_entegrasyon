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
    ->withMiddleware(function (Middleware $middleware) {
    // Ngrok gibi tüm dış proxylere güven
    $middleware->trustProxies(at: '*'); 
    
    // (Varsa önceki eklediğimiz CSRF ayarı da burada kalsın)
    $middleware->validateCsrfTokens(except: [
        'odeme/sonuc',
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();