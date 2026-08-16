<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\VersionContenido;
use App\Support\Publishing\DeployHook;
use App\Support\Publishing\PublicationScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Avisa al proveedor de alojamiento estático de que hay contenido nuevo que compilar.
 *
 * Se encola con retardo y lleva un testigo encima. Si al correr descubre que ya no es el
 * último de su tanda —porque alguien guardó otra palabra mientras esperaba—, se retira sin
 * hacer nada y deja el trabajo al más nuevo. Ese es el debounce; el detalle está en
 * `PublicationScheduler`.
 *
 * Sin testigo se publica de inmediato y sin debounce: es lo que hace `mam:publicar`.
 */
final class PublishSite implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Medio minuto y luego dos: una caída momentánea del proveedor no pierde la publicación. */
    public array $backoff = [30, 120];

    public function __construct(private readonly ?string $testigo = null) {}

    public function handle(DeployHook $hook): void
    {
        // El testigo se comprueba, pero no se borra. Si el proveedor falla y el trabajo se
        // reintenta, tiene que volver a pasar esta puerta; borrarlo aquí convertiría cada
        // reintento en una salida silenciosa.
        if ($this->testigo !== null && ! PublicationScheduler::isCurrent($this->testigo)) {
            return;
        }

        $hook->trigger(VersionContenido::numeroActual());
    }
}
