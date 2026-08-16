<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * La exigencia de contraseña, en un solo lugar.
 *
 * Hay tres puertas por las que se fija una contraseña —el comando de alta, el reseteo del
 * administrador y el cambio de la propia— y las tres tienen que pedir lo mismo. Repetir la
 * regla las dejaría desincronizadas el día que cambie.
 *
 * Ocho caracteres y nada más. Nada de exigir mayúsculas, números y símbolos: quien usa este
 * panel es personal docente de escuelas de San Marcos, y las reglas rebuscadas no producen
 * contraseñas mejores, producen contraseñas escritas en un papel pegado al monitor.
 *
 * Tampoco se usa `uncompromised()`, que consulta un servicio externo: en una conexión de
 * 3G intermitente convertiría el guardado en una espera sin explicación.
 */
final class PasswordRules
{
    private function __construct() {}

    public static function default(): Password
    {
        return Password::min(8);
    }
}
