<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege el endpoint de exportación con el token estático de `.env`.
 *
 * No es autenticación de usuario: lo consume el proceso de compilación del sitio estático,
 * que no tiene sesión ni navegador. Por eso un secreto compartido y no Sanctum.
 */
final class VerifyExportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('aprendemam.exportacion.token');

        // Sin token configurado el endpoint queda cerrado, no abierto. Una instalación
        // recién desplegada sin `EXPORT_TOKEN` no puede publicar el diccionario entero a
        // quien pase por la URL, que es justo lo que pasaría comparando dos cadenas vacías.
        if ($esperado === '') {
            abort(503, 'La exportación no está configurada: falta EXPORT_TOKEN.');
        }

        // `hash_equals` y no `===`: la comparación tarda lo mismo acierte o falle, así que
        // no filtra cuántos caracteres del token son correctos.
        if (! hash_equals($esperado, (string) $request->bearerToken())) {
            abort(401, 'Token de exportación inválido.');
        }

        return $next($request);
    }
}
