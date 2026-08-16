<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Crea una cuenta del panel.
 *
 * No hay registro público —el sitio de estudiantes no tiene cuentas ni recoge datos
 * personales, porque sus usuarios son menores de edad—, así que este comando es la única
 * puerta por la que entra un usuario al sistema. Es también lo que el docente responsable
 * necesita para dar de alta al validador lingüístico sin depender del desarrollador.
 *
 * La contraseña siempre se pide de forma interactiva y nunca por opción: un argumento de
 * línea de órdenes queda escrito en el historial del intérprete.
 */
class CreateUser extends Command
{
    protected $signature = 'mam:crear-usuario
                            {--nombre= : Nombre de la persona}
                            {--correo= : Correo con el que entra al panel}
                            {--rol= : administrador o editor}';

    protected $description = 'Crea una cuenta del panel de administración';

    public function handle(): int
    {
        // La contraseña se pide siempre, así que sin terminal no hay nada que hacer. El
        // aviso no es cosmético: el respaldo de `password()` fuera de terminal devuelve
        // cadena vacía, la validación de obligatorio la rechaza y el comando se queda
        // reintentando para siempre. Preferible salir con un mensaje.
        if (! $this->input->isInteractive()) {
            $this->error('Este comando pide la contraseña de forma interactiva y no puede correr con --no-interaction.');

            return self::FAILURE;
        }

        $nombre = $this->option('nombre') ?: text(
            label: 'Nombre',
            placeholder: 'María López',
            required: true,
        );

        $correo = $this->option('correo') ?: text(
            label: 'Correo electrónico',
            placeholder: 'maria@ejemplo.edu.gt',
            required: true,
        );

        $rol = $this->option('rol') ?: select(
            label: 'Rol',
            options: [
                UserRole::Editor->value => 'Editor — carga y corrige contenido',
                UserRole::Administrator->value => 'Administrador — además borra y marca lo revisado',
            ],
            default: UserRole::Editor->value,
        );

        $clave = password(label: 'Contraseña', required: true);
        $confirmacion = password(label: 'Repetí la contraseña', required: true);

        $datos = [
            'name' => $nombre,
            'email' => $correo,
            'rol' => $rol,
            'password' => $clave,
            'password_confirmation' => $confirmacion,
        ];

        $validador = Validator::make($datos, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'password' => ['required', 'confirmed', PasswordRules::default()],
        ], [
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
            'rol.in' => 'El rol debe ser «administrador» o «editor».',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = new User([
            'name' => $nombre,
            'email' => $correo,
            'password' => $clave, // El cast `hashed` del modelo se encarga.
        ]);

        // `rol` no es asignable en masa a propósito; se asigna aquí, explícitamente.
        $usuario->rol = UserRole::from($rol);
        $usuario->save();

        $this->newLine();
        $this->info("Cuenta creada: {$usuario->email} ({$usuario->rol->label()})");
        $this->line('Puede entrar en '.rtrim((string) config('app.url'), '/').'/admin/login');

        return self::SUCCESS;
    }
}
