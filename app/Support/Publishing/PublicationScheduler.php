<?php

declare(strict_types=1);

namespace App\Support\Publishing;

use App\Jobs\PublishSite;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * El debounce de la publicación.
 *
 * El problema que resuelve: cargar cuarenta palabras seguidas tiene que producir **una**
 * compilación del sitio, no cuarenta. Y no basta con encolar el trabajo la primera vez y
 * descartar los siguientes, porque entonces la compilación arrancaría con las treinta y
 * nueve palabras restantes sin escribir.
 *
 * Cómo funciona. Cada guardado escribe un testigo nuevo en caché y encola un trabajo
 * retardado que lleva ese testigo encima. Cuando un trabajo por fin corre, compara el suyo
 * con el que hay guardado: si no coinciden, alguien guardó después de él y hay un trabajo
 * más nuevo en camino, así que se retira sin hacer nada. Solo el último de la tanda
 * encuentra su propio testigo y dispara la compilación.
 *
 * Es la lectura literal de «el trabajo se sustituye a sí mismo en cada nueva modificación»
 * de la Especificación Técnica §2.2.
 */
final class PublicationScheduler
{
    private const CLAVE = 'publicacion.testigo';

    /**
     * El testigo tiene que sobrevivir holgadamente al retardo y a los reintentos del
     * trabajo. Una hora es de sobra y no deja basura: cada guardado lo reemplaza.
     */
    private const VIDA_TESTIGO_SEGUNDOS = 3600;

    private function __construct() {}

    public static function schedule(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        $testigo = (string) Str::uuid();

        Cache::put(self::CLAVE, $testigo, self::VIDA_TESTIGO_SEGUNDOS);

        PublishSite::dispatch($testigo)->delay(
            now()->addSeconds(self::delaySeconds())
        );
    }

    /** ¿Es este trabajo el último de la tanda, o ya lo sustituyó otro más nuevo? */
    public static function isCurrent(string $testigo): bool
    {
        return Cache::get(self::CLAVE) === $testigo;
    }

    /**
     * Sin deploy hook no hay a quién avisar, así que no se encola nada. Evita llenar la
     * cola de una instalación de desarrollo con trabajos que no harían nada, y evita que
     * una copia local dispare compilaciones de producción por accidente.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('aprendemam.publicacion.habilitada')
            && config('aprendemam.publicacion.deploy_hook_url') !== '';
    }

    public static function delaySeconds(): int
    {
        return max(0, (int) config('aprendemam.publicacion.retardo_segundos'));
    }
}
