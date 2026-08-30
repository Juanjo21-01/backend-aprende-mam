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
    /** El mínimo, en un solo sitio: lo usan la regla y el mensaje que la explica. */
    public const MINIMO = 8;

    private function __construct() {}

    public static function default(): Password
    {
        return Password::min(self::MINIMO);
    }

    /**
     * El mensaje de longitud, que sin esto sale como `validation.min.string` en pantalla.
     *
     * El proyecto no publica los archivos de idioma de Laravel, así que **toda regla sin
     * mensaje propio enseña su clave de traducción**. Las demás las cubre cada FormRequest
     * una por una; esta vive acá porque el número que menciona sale de esta misma clase y
     * porque son cinco las puertas por las que se fija una contraseña —el alta por consola,
     * el cambio por consola, el alta desde el panel, el reseteo y el cambio de la propia—
     * y las cinco tienen que decir lo mismo.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'password.min' => 'La contraseña necesita al menos '.self::MINIMO.' caracteres.',
        ];
    }
}
