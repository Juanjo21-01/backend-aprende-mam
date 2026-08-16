<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VersionContenido;
use Illuminate\Database\Eloquent\Model;

/**
 * Sube el número de versión cuando cambia algo que el sitio público publica.
 *
 * Es lo que enlaza el panel con la compilación de Astro: mientras el número no se mueva, el
 * build sabe que no hay nada nuevo que compilar y el Service Worker de los clientes no
 * tiene por qué renovar su caché.
 *
 * Escucha `created`, `updated` y `deleted` sobre `Entrada`, `Categoria` y `Fuente`. Las
 * tres salen en el JSON exportado: la entrada, el tema por el que se navega y el título de
 * la fuente que se cita al pie.
 *
 * Cuando se escriba el importador del COLIMAM tendrá que envolverse en `withoutEvents()` y
 * subir la versión una sola vez al final, o hará 6,185 incrementos por una sola publicación.
 * `DatabaseSeeder` ya usa `WithoutModelEvents`, así que sembrar no la mueve.
 */
final class ContentVersionObserver
{
    public function created(Model $model): void
    {
        VersionContenido::incrementar();
    }

    public function updated(Model $model): void
    {
        VersionContenido::incrementar();
    }

    public function deleted(Model $model): void
    {
        VersionContenido::incrementar();
    }
}
