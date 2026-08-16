<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Un administrador le pone contraseña nueva a otra persona.
 *
 * **No pide la contraseña anterior, y eso es el punto**: quien la olvidó no la sabe. Lo que
 * autoriza la operación no es conocer la clave vieja sino ser administrador, que lo
 * comprueba `UserPolicy::resetPassword()`.
 *
 * Es el camino normal de recuperación en este sistema, en lugar del correo. Con tres o
 * cuatro cuentas resulta más fiable: no depende de que el SMTP del alojamiento entregue, ni
 * de que el mensaje no caiga en spam, ni de que alguien tenga señal para abrirlo.
 */
final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', PasswordRules::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Falta la contraseña nueva.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ];
    }
}
