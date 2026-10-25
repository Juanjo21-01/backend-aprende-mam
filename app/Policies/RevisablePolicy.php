<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo de `ContentPolicy` más la revisión lingüística, para el contenido que pasa por el
 * validador: páginas y documentos.
 *
 * Marcar algo como revisado es dar fe de que el Mam está bien escrito, y eso corresponde al
 * validador lingüístico, que en este sistema es el administrador. Es la habilidad que
 * sostiene la publicación: al sitio solo llega lo revisado.
 *
 * `EntradaPolicy` tiene la suya propia, con el mismo criterio y su mensaje en femenino.
 */
abstract class RevisablePolicy extends ContentPolicy
{
    public function revisar(User $user, Model $model): Response
    {
        return $user->isAdministrator()
            ? Response::allow()
            : Response::deny('Solo el validador lingüístico puede marcar este contenido como revisado.');
    }
}
