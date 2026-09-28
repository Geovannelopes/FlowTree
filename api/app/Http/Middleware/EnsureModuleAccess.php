<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que o usuário autenticado tenha acesso ao módulo informado.
 * Uso na rota: ->middleware('module:tasks'). Admin passa em todos.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Não autenticado ou usuário inativo.'], 401);
        }

        if (! $user->hasModule($module)) {
            return response()->json(['message' => "Acesso negado ao módulo \"{$module}\"."], 403);
        }

        return $next($request);
    }
}
