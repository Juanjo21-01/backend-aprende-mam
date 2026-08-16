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

];
