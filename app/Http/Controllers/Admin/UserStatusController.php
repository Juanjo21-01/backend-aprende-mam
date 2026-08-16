<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ActiveSessions;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Activar o desactivar una cuenta: la baja del panel, sin borrar nada.
 *
 * Las dos guardas que impiden quedarse fuera del propio sistema —no desactivarse a uno
 * mismo, no desactivar al último administrador activo— están en `UserStatusRequest`, para
 * que respondan un 422 explicado y no una excepción a mitad del guardado.
 */
final class UserStatusController extends Controller
{
    #[Authorize('changeStatus', 'usuario')]
    public function update(UserStatusRequest $request, User $usuario): UserResource
    {
        $usuario->activo = $request->boolean('activo');
        $usuario->save();

        // Desactivar tiene que surtir efecto ahora, no cuando expire la sesión dentro de
        // dos horas. `EnsureUserIsActive` es el segundo cerrojo por si quedara alguna viva.
        if (! $usuario->activo) {
            ActiveSessions::revokeFor($usuario);
        }

        return UserResource::make($usuario);
    }
}
