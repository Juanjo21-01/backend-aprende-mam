<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * La regla de los dos roles, en un solo lugar.
 *
 * El editor carga y corrige contenido; el administrador además borra. Es lo que hace
 * viable el flujo que describe el Manual de Normas —cargar rápido y revisar después—
 * sin que una sesión de carga pueda perder trabajo ajeno por un clic.
 *
 * Vive en una clase base y no repetida en cada política porque es *una* regla: si mañana
 * cambia, tiene que cambiar para las tres entidades a la vez o se vuelve incoherente.
 * `EntradaPolicy` añade encima lo suyo, que es la revisión lingüística.
 */
abstract class ContentPolicy
{
    /** Ambos roles trabajan sobre el mismo contenido; estar dentro del panel ya es el filtro. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        return true;
    }

    /**
     * Borrar es lo único destructivo del panel y no tiene deshacer: queda para el
     * administrador. Se devuelve un `Response` con motivo para que el panel pueda decir
     * *por qué* no se pudo, en lugar de un 403 mudo.
     */
    public function delete(User $user, Model $model): Response
    {
        return $user->isAdministrator()
            ? Response::allow()
            : Response::deny('Solo un administrador puede borrar contenido.');
    }
}
