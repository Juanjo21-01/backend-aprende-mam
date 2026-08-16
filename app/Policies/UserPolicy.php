<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Gestionar cuentas es cosa de administradores, y de nadie más.
 *
 * No hereda de `ContentPolicy`: allí la regla es que ambos roles trabajan sobre el mismo
 * contenido y solo el borrado se reserva. Aquí es al revés, todo se reserva. Un editor no
 * tiene por qué ver siquiera la lista de cuentas del sistema.
 *
 * No hay habilidad `delete`: las cuentas no se borran, se desactivan. Una cuenta borrada no
 * vuelve y se lleva el rastro de quién trabajó en el contenido.
 */
final class UserPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->soloAdministradores($user);
    }

    public function view(User $user, User $objetivo): Response
    {
        return $this->soloAdministradores($user);
    }

    public function create(User $user): Response
    {
        return $this->soloAdministradores($user);
    }

    public function update(User $user, User $objetivo): Response
    {
        return $this->soloAdministradores($user);
    }

    /** Poner una contraseña nueva a otra persona, sin conocer la anterior. */
    public function resetPassword(User $user, User $objetivo): Response
    {
        return $this->soloAdministradores($user);
    }

    public function changeStatus(User $user, User $objetivo): Response
    {
        return $this->soloAdministradores($user);
    }

    private function soloAdministradores(User $user): Response
    {
        return $user->isAdministrator()
            ? Response::allow()
            : Response::deny('Solo un administrador puede gestionar las cuentas del panel.');
    }
}
