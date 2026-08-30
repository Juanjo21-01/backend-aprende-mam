<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ActiveSessions;
use App\Support\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * El último recurso cuando alguien se queda fuera del panel.
 *
 * El camino normal de recuperación es que un administrador resetee la contraseña desde el
 * panel. Este comando existe para el caso que ese camino no cubre: el **administrador único
 * que olvida su propia clave** y por tanto no tiene quién se la cambie.
 *
 * Requiere acceso al servidor, y eso es exactamente lo que lo hace válido como rescate: el
 * alojamiento está a nombre del docente responsable desde el primer día, así que quien
 * puede quedarse fuera del panel es también quien tiene la terminal.
 *
 * Como en el alta, la contraseña se pide de forma interactiva y nunca por opción: un
 * argumento de línea de órdenes queda escrito en el historial del intérprete.
 */
class ChangeUserPassword extends Command
{
    protected $signature = 'mam:cambiar-contrasena
                            {--correo= : Correo de la cuenta}';

    protected $description = 'Cambia la contraseña de una cuenta del panel desde la consola';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando pide la contraseña de forma interactiva y no puede correr con --no-interaction.');

            return self::FAILURE;
        }

        $correo = $this->option('correo') ?: text(
            label: 'Correo de la cuenta',
            placeholder: 'maria@ejemplo.edu.gt',
            required: true,
        );

        $usuario = User::query()->where('email', $correo)->first();

        if ($usuario === null) {
            $this->error("No hay ninguna cuenta con el correo «{$correo}».");

            return self::FAILURE;
        }

        $this->line("Cuenta: <options=bold>{$usuario->name}</> ({$usuario->rol->label()})");

        if (! $usuario->activo) {
            $this->warn('Ojo: esta cuenta está desactivada. Cambiarle la contraseña no la habilita;');
            $this->warn('eso se hace desde el panel, o volviendo a activarla ahí.');
        }

        $clave = password(label: 'Contraseña nueva', required: true);
        $confirmacion = password(label: 'Repetí la contraseña', required: true);

        $validador = Validator::make(
            ['password' => $clave, 'password_confirmation' => $confirmacion],
            ['password' => ['required', 'confirmed', PasswordRules::default()]],
            [
                ...PasswordRules::messages(),
                'password.confirmed' => 'Las dos contraseñas no coinciden.',
            ]
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario->password = $clave; // El cast `hashed` del modelo se encarga.
        $usuario->save();

        // Igual que en el reseteo desde el panel: si la contraseña se cambió porque se
        // sospecha que alguien más entró, dejar vivas sus sesiones anularía la medida.
        ActiveSessions::revokeFor($usuario);

        $this->newLine();
        $this->info("Contraseña actualizada para {$usuario->email}.");
        $this->line('Las sesiones que tuviera abiertas quedaron cerradas.');

        return self::SUCCESS;
    }
}
