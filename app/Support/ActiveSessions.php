<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Cierra por la fuerza las sesiones abiertas de un usuario.
 *
 * Dos momentos lo necesitan y por el mismo motivo: al desactivar una cuenta y al resetearle
 * la contraseña. En ambos, la intención es que esa persona deje de tener acceso **ahora**,
 * y una sesión ya abierta no se entera de que la contraseña cambió: la cookie sigue siendo
 * válida hasta que expire.
 *
 * Solo funciona con las sesiones guardadas en base, que es la configuración del proyecto.
 * Con otro almacén no hay tabla que borrar y la comprobación de `EnsureUserIsActive` queda
 * como única defensa.
 */
final class ActiveSessions
{
    private function __construct() {}

    public static function revokeFor(User $user): void
    {
        if (Config::get('session.driver') !== 'database') {
            return;
        }

        DB::table(Config::get('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
