<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VersionContenido;
use App\Support\Publishing\PublicationScheduler;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que pasa cada vez que cambia algo que el sitio público publica.
 *
 * Dos cosas, y en este orden: sube el número de versión, y programa la publicación con
 * retardo. Escucha `created`, `updated` y `deleted` sobre `Entrada`, `Categoria` y
 * `Fuente`, que son las tres que salen en el JSON exportado.
 *
 * **La versión sube acá y no en el trabajo de publicación**, a diferencia de lo que sugiere
 * la Especificación Técnica §2.2. El motivo: así el número refleja el estado del contenido
 * en todo momento, y `GET /export/version` responde con la verdad aunque la publicación
 * esté todavía esperando su retardo. Si solo subiera al publicar, una compilación lanzada a
 * mano en ese hueco emitiría contenido nuevo con número viejo, y el trabajo pendiente
 * dispararía otra compilación al rato para nada. La intención del documento —que una sesión
 * de carga produzca una sola compilación— la cumple el debounce, no quién incrementa.
 *
 * Para la importación del corpus: envolverla en `withoutEvents()` o apagar
 * `PUBLICACION_HABILITADA`, o serán 6,185 incrementos y otros tantos trabajos encolados por
 * una sola publicación. `DatabaseSeeder` ya usa `WithoutModelEvents`, así que sembrar no
 * mueve nada.
 */
final class PublishableContentObserver
{
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
        VersionContenido::incrementar();

        PublicationScheduler::schedule();
    }
}
