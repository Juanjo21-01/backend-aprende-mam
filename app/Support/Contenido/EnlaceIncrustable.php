<?php

declare(strict_types=1);

namespace App\Support\Contenido;

use Closure;

/**
 * Qué video o actividad externa se puede asociar a una página o a un documento.
 *
 * **Espeja `src/data/incrustado.ts` del frontend**, y tiene que seguir haciéndolo: el
 * frontend hace fallar la compilación si recibe una dirección que no entiende, así que
 * dejarla pasar aquí equivale a tumbar la publicación entera por un error de tipeo. Validar
 * en el panel convierte ese fallo del build en un mensaje junto al campo.
 *
 * Las reglas son las mismas, una por una:
 *
 *  - Solo `https`.
 *  - Lista cerrada de dominios; un subdominio de cualquiera vale, un dominio que solo
 *    *contiene* el nombre (`evilyoutube.com`, `youtube.com.evil.com`) no.
 *  - De YouTube hay que poder sacar el identificador de 11 caracteres de las formas `watch`,
 *    `youtu.be`, `embed`, `shorts` y `live`.
 *  - Se acepta el código `<iframe>` que da el proveedor: se extrae su `src` y el HTML se
 *    descarta. Lo que se guarda es siempre una URL.
 *
 * Si se agrega un proveedor en `incrustado.ts`, se agrega también en `DOMINIOS`.
 * El video nunca se aloja: es solo la dirección.
 */
final class EnlaceIncrustable
{
    /** @var array<string, list<string>> */
    private const DOMINIOS = [
        'youtube' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
        'wordwall' => ['wordwall.net'],
        'educaplay' => ['educaplay.com'],
    ];

    /** `video_url` es `varchar(500)`. */
    private const LARGO_MAXIMO = 500;

    private const ID_YOUTUBE = '/^[A-Za-z0-9_-]{11}$/';

    private function __construct() {}

    /**
     * Si lo que llegó es el código `<iframe>`, devuelve su `src`; si no, la entrada sin
     * espacios. No valida: para eso está `problema()`.
     */
    public static function extraer(string $entrada): string
    {
        $texto = trim($entrada);

        if (! str_starts_with($texto, '<')) {
            return $texto;
        }

        $patron = '/<iframe\b[^>]*?\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

        if (preg_match($patron, $texto, $coincidencia) !== 1) {
            return $texto;
        }

        $src = ($coincidencia[1] ?? '') !== '' ? $coincidencia[1] : ($coincidencia[2] ?? '');

        // Los códigos de inserción escapan el `&` de la consulta como `&amp;`.
        return str_replace('&amp;', '&', $src);
    }

    /**
     * Motivo por el que no se puede incrustar, o `null` si sirve.
     */
    public static function problema(string $entrada): ?string
    {
        $url = self::extraer($entrada);

        if (str_starts_with($url, '<')) {
            return 'se pegó código HTML, pero no trae un <iframe> con su dirección.';
        }

        // El tope de la columna. Va aquí y no en el request porque se mide la URL ya
        // extraída: un código `<iframe>` completo mide más que la dirección que se guarda.
        if (strlen($url) > self::LARGO_MAXIMO) {
            return 'es demasiado larga.';
        }

        $partes = parse_url($url);

        if ($partes === false || ! isset($partes['scheme'], $partes['host'])) {
            return 'no es una dirección web.';
        }

        if (strtolower($partes['scheme']) !== 'https') {
            return 'solo se aceptan direcciones https.';
        }

        $host = strtolower($partes['host']);
        $proveedor = self::proveedorDe($host);

        if ($proveedor === null) {
            return "{$host} no está entre los proveedores permitidos (YouTube, Wordwall, Educaplay).";
        }

        if ($proveedor === 'youtube') {
            $id = self::idDeYoutube($host, $partes);

            if ($id === null || preg_match(self::ID_YOUTUBE, $id) !== 1) {
                return 'es de YouTube, pero no se encontró el identificador del video.';
            }
        }

        return null;
    }

    /**
     * Regla de validación para los FormRequest. Acepta la URL o el código `<iframe>`.
     *
     * @return Closure(string, mixed, Closure): void
     */
    public static function regla(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            if (! is_string($valor)) {
                return;
            }

            $motivo = self::problema($valor);

            if ($motivo !== null) {
                $fallar("La dirección del video {$motivo}");
            }
        };
    }

    /** `youtube.com.evil.com` no pertenece a `youtube.com`: se compara el dominio entero. */
    private static function perteneceA(string $host, string $dominio): bool
    {
        return $host === $dominio || str_ends_with($host, ".{$dominio}");
    }

    private static function proveedorDe(string $host): ?string
    {
        foreach (self::DOMINIOS as $proveedor => $dominios) {
            foreach ($dominios as $dominio) {
                if (self::perteneceA($host, $dominio)) {
                    return $proveedor;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $partes
     */
    private static function idDeYoutube(string $host, array $partes): ?string
    {
        $ruta = (string) ($partes['path'] ?? '');
        $segmentos = array_values(array_filter(explode('/', $ruta), static fn (string $s): bool => $s !== ''));

        if (self::perteneceA($host, 'youtu.be')) {
            return $segmentos[0] ?? null;
        }

        $primero = $segmentos[0] ?? null;

        if ($primero === 'watch') {
            parse_str((string) ($partes['query'] ?? ''), $consulta);

            return is_string($consulta['v'] ?? null) ? $consulta['v'] : null;
        }

        if (in_array($primero, ['embed', 'shorts', 'live'], true)) {
            return $segmentos[1] ?? null;
        }

        return null;
    }
}
