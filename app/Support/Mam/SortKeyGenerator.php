<?php

declare(strict_types=1);

namespace App\Support\Mam;

use RuntimeException;

/**
 * Genera la clave de intercalación que se guarda en `orden_alfabetico`.
 *
 * Cada grafema se sustituye por su posición en dos dígitos y se concatena:
 * `tz’is` → `[tz’][i][s]` → 26 06 20 → `"260620"`. Comparar esas claves como cadenas reproduce
 * el orden del Mam, que no es el del castellano: `chmol` va antes que `ch’el` y `xaq` antes que
 * `ẍiky`.
 *
 * Es lo que permite no usar nunca `ORDER BY mam`: `utf8mb4_unicode_ci` ni siquiera distingue `x`
 * de `ẍ`. La clave es ASCII de dígitos, así que ese mismo cotejamiento la ordena bien.
 */
final class SortKeyGenerator
{
    public function __construct(private readonly Tokenizer $tokenizer) {}

    public function generate(string $text): string
    {
        return $this->fromGraphemes($this->tokenizer->tokenize($text));
    }

    /**
     * Para cuando el llamador ya tokenizó y no tiene sentido repetirlo.
     *
     * @param  list<Grapheme>  $graphemes
     */
    public function fromGraphemes(array $graphemes): string
    {
        $key = '';

        foreach ($graphemes as $grapheme) {
            $key .= self::code($grapheme->position);
        }

        return $key;
    }

    private static function code(?int $position): string
    {
        // Fuera del alfabeto: el espacio de un lema de dos palabras, un guion, la puntuación de
        // una oración. `00` ordena antes que `a` = 01, que es la convención de diccionario:
        // «ja la» antes que «jala».
        if ($position === null) {
            return '00';
        }

        // Si COLIMAM llegara a ratificar un alfabeto de más de 99 grafemas, la clave de dos
        // dígitos dejaría de ordenar y el fallo sería invisible: `100` y `10` empiezan igual.
        if ($position < 1 || $position > 99) {
            throw new RuntimeException(sprintf(
                'La posición %d no cabe en una clave de dos dígitos. Ampliar el ancho de la clave '.
                'y regenerar `orden_alfabetico` en toda la tabla.',
                $position
            ));
        }

        return str_pad((string) $position, 2, '0', STR_PAD_LEFT);
    }
}
