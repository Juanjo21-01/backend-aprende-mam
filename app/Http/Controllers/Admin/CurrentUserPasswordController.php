<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOwnPasswordRequest;
use Illuminate\Http\JsonResponse;

/**
 * Cambiar la contraseña propia. Lo puede hacer cualquiera de los dos roles sobre su cuenta,
 * y sobre ninguna otra: no hay identificador en la ruta, siempre opera sobre quien entró.
 *
 * Pide la contraseña actual, al revés que el reseteo del administrador. Con eso, un panel
 * abierto y desatendido en la computadora de la escuela no sirve para cambiarle la clave a
 * su dueño y dejarlo fuera.
 */
final class CurrentUserPasswordController extends Controller
{
    public function update(UpdateOwnPasswordRequest $request): JsonResponse
    {
        $usuario = $request->user();

        $usuario->password = $request->validated('password'); // El cast `hashed` se encarga.
        $usuario->save();

        // La sesión actual sobrevive al cambio —quien la está usando acaba de demostrar que
        // sabe la contraseña anterior—, pero con identificador nuevo: si alguien había
        // copiado la cookie, deja de servirle.
        //
        // La comprobación no sobra: en el grupo `api` la sesión solo existe cuando Sanctum
        // reconoce la petición como del panel. Si llegara por otro camino, `session()`
        // lanzaría en lugar de simplemente no regenerar nada.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['mensaje' => 'Contraseña actualizada.']);
    }
}
