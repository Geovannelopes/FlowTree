<?php

use App\Http\Middleware\EnsureModuleAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás do nginx do front (e de um proxy/Cloudflare em produção):
        // lê o IP real e o esquema via X-Forwarded-*.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'module' => EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API pura: todo erro vira JSON, mesmo sem Accept: application/json.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Token expirado/inválido/na blacklist → 401 (dispara o refresh no front).
        $exceptions->render(function (JWTException $e, $request) {
            return response()->json(['message' => 'Sessão inválida ou expirada.'], 401);
        });
    })->create();
