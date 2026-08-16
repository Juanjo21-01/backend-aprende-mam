<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Echa del panel a quien tenga la cuenta desactivada.
 *
 * Hace falta porque desactivar a alguien no toca su sesión abierta: el guard solo comprueba
 * que la cookie sea válida, no en qué estado quedó la cuenta desde entonces. Sin esto, una
 * cuenta desactivada seguiría trabajando hasta que la sesión expirara —dos horas con la
 * configuración actual—, que es justo el rato en el que uno quiere que deje de trabajar.
 *
 * Las sesiones guardadas en base se borran al desactivar, así que esto es el segundo
 * cerrojo: cubre las que estuvieran en otro almacén o las que se creen entre medio.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null || $usuario->activo) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        abort(403, 'Esta cuenta está desactivada. Pedile a un administrador que la habilite.');
    }
}
