<?php

declare(strict_types=1);

namespace App\Support\Contenido;

/**
 * Qué direcciones puede llevar el contenido editorial, y cuáles no.
 *
 * Dos destinos y nada más: una URL **https** o una **ruta del sitio** (`/img/mapa.png`,
 * `/materiales/guia.pdf`). Las rutas son las de los archivos que viven en el frontend, que es
 * donde se colocan las imágenes y los PDF porque este backend no los aloja.
 *
 * Lo que se descarta importa tanto como lo que se admite: `http:` se puede interceptar en
 * una red de escuela, y `javascript:` y `data:` ejecutan código desde un enlace en un sitio
 * para menores. Una ruta que empieza con `//` tampoco vale: el navegador la lee como un host
 * ajeno y se salta la regla de https.
 */
final class Enlaces
{
    private function __construct() {}

    public static function esHttps(string $destino): bool
    {
        if (! str_starts_with(strtolower($destino), 'https://')) {
            return false;
        }

        $host = parse_url($destino, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }

    public static function esRutaDelSitio(string $destino): bool
    {
        return str_starts_with($destino, '/')
            && ! str_starts_with($destino, '//')
            && ! str_contains($destino, '\\');
    }

    /**
     * Para el destino de un enlace del cuerpo; el ancla (`#seccion`) solo vale en enlaces,
     * no en imágenes.
     */
    public static function esDestinoSeguro(string $destino, bool $permiteAncla = false): bool
    {
        if (self::esHttps($destino) || self::esRutaDelSitio($destino)) {
            return true;
        }

        return $permiteAncla && str_starts_with($destino, '#') && strlen($destino) > 1;
    }

    /**
     * Servicios donde el docente aloja sus PDF y cuyas direcciones no terminan en `.pdf`: el
     * enlace de «Compartir» de Drive es `drive.google.com/file/d/ID/view`, y el de descarga
     * directa, `drive.google.com/uc?export=download&id=ID`. Lista cerrada, como los dominios del
     * video: lo que no esté aquí tiene que apuntar a un `.pdf`.
     */
    private const HOSTS_DE_ARCHIVOS = ['drive.google.com', 'drive.usercontent.google.com'];

    /**
     * Motivo por el que no sirve como archivo PDF descargable, o `null` si sirve.
     */
    public static function problemaDeArchivoPdf(string $destino): ?string
    {
        if (! self::esHttps($destino) && ! self::esRutaDelSitio($destino)) {
            return 'debe ser una dirección https o una ruta del sitio que empiece con «/».';
        }

        $host = parse_url($destino, PHP_URL_HOST);

        if (self::esHttps($destino) && is_string($host)
            && in_array(strtolower($host), self::HOSTS_DE_ARCHIVOS, true)) {
            return null;
        }

        $ruta = parse_url($destino, PHP_URL_PATH);

        if (! is_string($ruta) || ! str_ends_with(strtolower($ruta), '.pdf')) {
            return 'debe apuntar a un archivo .pdf.';
        }

        return null;
    }
}
