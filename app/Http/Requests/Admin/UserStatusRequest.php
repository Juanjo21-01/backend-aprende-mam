<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Activar o desactivar una cuenta.
 *
 * La baja del panel es esta bandera, no un borrado: una cuenta desactivada se vuelve a
 * habilitar en un clic y conserva el rastro de quién trabajó en el contenido.
 */
final class UserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $usuario = $this->route('usuario');

            if (! $usuario instanceof User || $this->boolean('activo')) {
                return;
            }

            // Las dos formas de quedarse fuera del propio panel para siempre.
            if ($usuario->is($this->user())) {
                $validator->errors()->add('activo', 'No podés desactivar tu propia cuenta.');
            }

            if ($usuario->isLastActiveAdministrator()) {
                $validator->errors()->add(
                    'activo',
                    'Es el único administrador activo. Nombrá a otro antes de desactivarlo.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activo.required' => 'Falta indicar si la cuenta queda activa o no.',
        ];
    }
}
