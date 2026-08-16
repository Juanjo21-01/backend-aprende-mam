<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quién entró y con qué rol.
 *
 * Lo primero que pide el panel al arrancar: sin esto no sabe si dibujar los botones de
 * borrar ni el interruptor de revisión. La comprobación de verdad la hacen las políticas
 * en el servidor; esto solo evita ofrecer una acción que va a terminar en 403.
 */
final class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'rol' => $usuario->rol->value,
            'rol_nombre' => $usuario->rol->label(),
            'es_administrador' => $usuario->isAdministrator(),
        ]);
    }
}
