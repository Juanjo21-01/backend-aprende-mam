<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\VerifyExportToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El panel lo sirve este mismo Laravel desde /admin, así que las peticiones del
        // panel llegan con la cookie de sesión. Sin esto el grupo `api` es sin estado y
        // `auth:sanctum` exigiría un token que nunca se emite.
        $middleware->statefulApi();

        $middleware->alias([
            'token.exportacion' => VerifyExportToken::class,
            'usuario.activo' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
