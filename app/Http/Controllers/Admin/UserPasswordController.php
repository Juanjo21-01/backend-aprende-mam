<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ActiveSessions;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * El camino de recuperación de este sistema: un administrador le pone contraseña nueva a
 * quien la olvidó.
 *
 * Reemplaza al flujo por correo a propósito. Con tres o cuatro cuentas resulta más fiable
 * que depender de que el SMTP del alojamiento compartido entregue, de que el mensaje no
 * caiga en spam y de que el destinatario tenga señal para abrirlo. Para el caso del
 * administrador único que se queda fuera —y por tanto no tiene quién se la resetee— está
 * `php artisan mam:cambiar-contrasena`.
 */
final class UserPasswordController extends Controller
{
    #[Authorize('resetPassword', 'usuario')]
    public function update(ResetPasswordRequest $request, User $usuario): UserResource
    {
        $usuario->password = $request->validated('password'); // El cast `hashed` se encarga.
        $usuario->save();

        // Las sesiones que esa persona tuviera abiertas siguen siendo válidas aunque la
        // contraseña haya cambiado: la cookie no sabe nada de eso. Si el reseteo fue porque
        // se sospecha que alguien más entró, dejarlas vivas anularía la medida entera.
        ActiveSessions::revokeFor($usuario);

        return UserResource::make($usuario);
    }
}
