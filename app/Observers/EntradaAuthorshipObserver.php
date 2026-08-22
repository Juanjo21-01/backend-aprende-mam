<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Entrada;
use Illuminate\Support\Facades\Auth;

/**
 * Anota quién cargó cada entrada y quién la dio por revisada.
 *
 * Va en un observer y no en los controladores por el mismo motivo que la normalización va
 * en el mutator: así también lo cubren tinker y el futuro importador del corpus. Y porque
 * son tres columnas que nadie debería poder escribir a mano — no están en `#[Fillable]`.
 *
 * Sin sesión abierta, la autoría queda nula. Es lo correcto: una importación masiva no la
 * cargó una persona, y mentir diciendo que sí sería peor que no saberlo.
 */
final class EntradaAuthorshipObserver
{
    public function creating(Entrada $entrada): void
    {
        // `??=` para que el importador pueda declarar la autoría explícitamente si algún día
        // llega a saberla; lo que no puede es sobrescribirse sola en cada guardado.
        $entrada->creado_por ??= Auth::id();
    }

    /**
     * La revisión se anota cuando la bandera **cambia**, no en cada guardado: corregir una
     * tilde de la traducción no debe reescribir la fecha en que se validó el Mam.
     */
    public function saving(Entrada $entrada): void
    {
        if ($entrada->exists && ! $entrada->isDirty('revisado')) {
            return;
        }

        if ($entrada->revisado) {
            $entrada->revisado_por = Auth::id();
            $entrada->revisado_en = now();

            return;
        }

        // Retirar la revisión borra el rastro a propósito: dejar el nombre del validador en
        // una entrada que ya no está aprobada haría creer que la aprobó.
        $entrada->revisado_por = null;
        $entrada->revisado_en = null;
    }
}
