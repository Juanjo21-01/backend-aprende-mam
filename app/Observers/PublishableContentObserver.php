<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VersionContenido;
use App\Support\Publishing\PublicationScheduler;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que pasa cada vez que cambia algo que el sitio público publica.
 *
 * Dos cosas, y en este orden: sube el número de versión, y programa la publicación con
 * retardo. Escucha `created`, `updated` y `deleted` sobre `Entrada`, `Categoria`, `Fuente`,
 * `Pagina` y `Documento`, que son las que salen en los JSON exportados (`vocabulario.json` y
 * `contenido.json` comparten el mismo número de versión).
 *
 * **La versión sube acá y no en el trabajo de publicación**, a diferencia de lo que sugiere
 * la Especificación Técnica §2.2. El motivo: así el número refleja el estado del contenido
 * en todo momento, y `GET /export/version` responde con la verdad aunque la publicación
 * esté todavía esperando su retardo. Si solo subiera al publicar, una compilación lanzada a
 * mano en ese hueco emitiría contenido nuevo con número viejo, y el trabajo pendiente
 * dispararía otra compilación al rato para nada. La intención del documento —que una sesión
 * de carga produzca una sola compilación— la cumple el debounce, no quién incrementa.
 *
 * Para la importación del corpus: envolverla en `withoutPublishing()`, o serán 6,185
 * incrementos y otros tantos trabajos encolados por una sola publicación. `DatabaseSeeder`
 * ya usa `WithoutModelEvents`, así que sembrar no mueve nada.
 */
final class PublishableContentObserver
{
    /** Contador y no booleano: una llamada anidada no reactiva la publicación al salir. */
    private static int $silenciado = 0;

    /**
     * Ejecuta `$callback` sin subir la versión ni programar publicación.
     *
     * Existe para la importación en lote, y se prefirió a `Model::withoutEvents()` porque ese
     * apaga **todos** los observadores, también el de autoría, que no tiene por qué callarse.
     * Lo que se calla aquí es solo lo que sería trabajo inútil: lo importado nace sin revisar
     * y no cambia la exportación.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withoutPublishing(Closure $callback): mixed
    {
        self::$silenciado++;

        try {
            return $callback();
        } finally {
            self::$silenciado--;
        }
    }

    public function created(Model $model): void
    {
        $this->registrarCambio();
    }

    public function updated(Model $model): void
    {
        $this->registrarCambio();
    }

    public function deleted(Model $model): void
    {
        $this->registrarCambio();
    }

    private function registrarCambio(): void
    {
        if (self::$silenciado > 0) {
            return;
        }

        VersionContenido::incrementar();

        PublicationScheduler::schedule();
    }
}
