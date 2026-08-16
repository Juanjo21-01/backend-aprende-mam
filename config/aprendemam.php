<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Exportación del vocabulario
    |--------------------------------------------------------------------------
    |
    | El sitio público no consulta esta API en tiempo de ejecución: el proceso de
    | compilación de Astro lee el vocabulario una vez y genera archivos estáticos.
    | Ese proceso es el único consumidor del endpoint de exportación, y se
    | identifica con un token estático.
    |
    | No es una credencial de usuario ni caduca: es un secreto compartido entre
    | dos máquinas. Se genera con `php artisan mam:token-exportacion` y se copia a
    | la variable de entorno del proveedor de compilación.
    |
    */

    'exportacion' => [
        'token' => env('EXPORT_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publicación del sitio estático
    |--------------------------------------------------------------------------
    |
    | Al guardar contenido se encola un trabajo con retardo que avisa al
    | proveedor de alojamiento estático para que recompile el sitio. El trabajo
    | se sustituye a sí mismo en cada nueva modificación, de modo que una sesión
    | de carga de cuarenta palabras produce una sola compilación y no cuarenta.
    |
    | Sin `DEPLOY_HOOK_URL` no se encola nada: en desarrollo no hay a quién
    | avisar, y encolar trabajos que no van a hacer nada solo ensucia la cola.
    |
    */

    'publicacion' => [
        'deploy_hook_url' => env('DEPLOY_HOOK_URL', ''),

        /*
         * Cinco minutos. Es el hueco que tiene que haber entre dos guardados para
         * que se consideren sesiones distintas. Cargar vocabulario a mano da un
         * ritmo de una palabra por minuto o menos, así que con menos retardo una
         * misma sesión dispararía varias compilaciones.
         *
         * Subirlo cuesta poco: la Especificación Técnica ya asume una latencia de
         * publicación de uno a tres minutos y la considera irrelevante para
         * contenido pedagógico.
         */
        'retardo_segundos' => (int) env('PUBLICACION_RETARDO_SEGUNDOS', 300),

        /*
         * Interruptor general. Apagarlo permite cargar contenido sin que se
         * dispare ninguna compilación: la importación del corpus, una migración
         * de datos, o simplemente trabajar sin publicar todavía.
         */
        'habilitada' => (bool) env('PUBLICACION_HABILITADA', true),
    ],

];
