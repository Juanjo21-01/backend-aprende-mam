<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entrada;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Lo de `ContentPolicy` más la revisión lingüística.
 */
final class EntradaPolicy extends ContentPolicy
{
    /**
     * Marcar una entrada como revisada es dar fe de que el Mam está bien escrito, y eso
     * corresponde al validador lingüístico, que en este sistema es el administrador.
     *
     * Es la habilidad que sostiene todo lo demás: al sitio público solo llega lo revisado,
     * así que quien pueda mover esta bandera decide qué se publica.
     */
    public function revisar(User $user, Entrada $entrada): Response
    {
        return $user->isAdministrator()
            ? Response::allow()
            : Response::deny('Solo el validador lingüístico puede marcar una entrada como revisada.');
    }
}
