<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambiar la contraseña propia.
 *
 * Sí pide la actual, al revés que el reseteo del administrador. El motivo es concreto: una
 * sesión abierta y desatendida —el panel en la computadora de la escuela— no puede servir
 * para cambiarle la contraseña a su dueño y dejarlo fuera.
 */
final class UpdateOwnPasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', PasswordRules::default(), Rule::notIn([$this->input('current_password')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...PasswordRules::messages(),
            'current_password.required' => 'Falta tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Falta la contraseña nueva.',
            'password.confirmed' => 'Las dos contraseñas nuevas no coinciden.',
            'password.not_in' => 'La contraseña nueva tiene que ser distinta de la actual.',
        ];
    }
}
