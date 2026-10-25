<?php

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Anota quién cargó cada pieza de contenido y quién la dio por revisada.
 *
 * Sirve a `Entrada`, `Pagina` y `Documento`: las tres comparten el flujo «se carga rápido y
 * el validador lingüístico firma después», y por tanto las mismas cuatro columnas
 * (`revisado`, `creado_por`, `revisado_por`, `revisado_en`).
 *
 * Va en un observer y no en los controladores por el mismo motivo que la normalización va
 * en el mutator: así también lo cubren tinker y el importador del corpus. Y porque son
 * columnas que nadie debería poder escribir a mano — no están en `#[Fillable]`.
 *
 * Sin sesión abierta, la autoría queda nula. Es lo correcto: una importación masiva no la
 * cargó una persona, y mentir diciendo que sí sería peor que no saberlo.
 */
final class RevisionAuthorshipObserver
{
    public function creating(Model $contenido): void
    {
        // `??=` para que el importador pueda declarar la autoría explícitamente si algún día
        // llega a saberla; lo que no puede es sobrescribirse sola en cada guardado.
        $contenido->creado_por ??= Auth::id();
    }

    /**
     * La revisión se anota cuando la bandera **cambia**, no en cada guardado: corregir una
     * tilde de la traducción no debe reescribir la fecha en que se validó el Mam.
     */
    public function saving(Model $contenido): void
    {
        if ($contenido->exists && ! $contenido->isDirty('revisado')) {
            return;
        }

        if ($contenido->revisado) {
            $contenido->revisado_por = Auth::id();
            $contenido->revisado_en = now();

            return;
        }

        // Retirar la revisión borra el rastro a propósito: dejar el nombre del validador en
        // algo que ya no está aprobado haría creer que lo aprobó.
        $contenido->revisado_por = null;
        $contenido->revisado_en = null;
    }
}
