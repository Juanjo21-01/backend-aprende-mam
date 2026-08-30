<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una cuenta del panel.
 *
 * Lleva dos guardas que no son de formato sino de sentido común operativo, y que están
 * aquí y no en el controlador para que fallen con un 422 explicado en lugar de con una
 * excepción a mitad de un guardado.
 */
final class UserRequest extends FormRequest
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
        $usuario = $this->usuarioEnEdicion();
        $obligatorio = $usuario === null ? ['required'] : ['sometimes', 'required'];

        return [
            'name' => [...$obligatorio, 'string', 'max:255'],

            'email' => [
                ...$obligatorio,
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario),
            ],

            'rol' => [...$obligatorio, Rule::enum(UserRole::class)],

            // Solo al crear: para cambiarla después está el reseteo, que es su propia
            // acción con su propia política y que además cierra las sesiones abiertas.
            'password' => $usuario === null
                ? ['required', 'confirmed', PasswordRules::default()]
                : ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $usuario = $this->usuarioEnEdicion();

            if ($usuario === null || ! $this->has('rol')) {
                return;
            }

            $nuevoRol = UserRole::tryFrom((string) $this->input('rol'));

            if ($nuevoRol === UserRole::Administrator) {
                return;
            }

            // Bajar a editor al último administrador dejaría el panel sin nadie que pueda
            // crear cuentas, borrar contenido ni aprobar una revisión. La única salida
            // sería la consola del servidor.
            if ($usuario->isLastActiveAdministrator()) {
                $validator->errors()->add(
                    'rol',
                    'Es el único administrador activo. Nombrá a otro antes de bajarle el rol.'
                );
            }

            // Aunque quedaran más administradores: quitarse el rol a uno mismo cierra la
            // puerta desde adentro y en medio de la sesión.
            if ($usuario->is($this->user())) {
                $validator->errors()->add('rol', 'No podés cambiarte el rol a vos mismo.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...PasswordRules::messages(),
            'required' => 'Falta :attribute.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
            'password.prohibited' => 'La contraseña se cambia desde la acción de reseteo, no editando la cuenta.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'el nombre',
            'email' => 'el correo',
            'rol' => 'el rol',
            'password' => 'la contraseña',
        ];
    }

    private function usuarioEnEdicion(): ?User
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User ? $usuario : null;
    }
}
