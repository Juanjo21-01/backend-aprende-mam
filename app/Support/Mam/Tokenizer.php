<?php

declare(strict_types=1);

namespace App\Support\Mam;

use LogicException;

/**
 * Parte texto en Mam en grafemas, por coincidencia más larga primero.
 *
 * Los dígrafos (`ch`, `ky`, `tx`, `tz`) y las glotalizadas (`b’`, `ch’`, `k’`, `ky’`, `q’`, `t’`,
 * `tx’`, `tz’`) son una sola letra, nunca una secuencia de dos. Partirlas no da ningún error: da
 * un orden alfabético equivocado y una segmentación mal contada.
 *
 * El inventario y el orden salen del catálogo `grafemas` vía `Alphabet`; aquí no hay ni un
 * grafema cableado, ni siquiera la longitud máxima.
 */
final class Tokenizer
{
    public function __construct(private readonly Alphabet $alphabet) {}

    /** @return list<Grapheme> */
    public function tokenize(string $text): array
    {
        // El corpus llega con U+0027 —el propio archivo de reglas está escrito así— y el catálogo
        // guarda U+2019. Sin este paso, `tz'is` con apóstrofo recto se partiría en
        // `[t] [z] ['] [i] [s]`: cinco grafemas en lugar de tres, y ninguna excepción. La
        // normalización es idempotente, así que volver a llamarla desde el mutator no cuesta nada.
        $text = TextNormalizer::normalize($text);

        if ($text === '') {
            return [];
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Minúsculas carácter por carácter, no sobre la cadena entera: si una caja cambiara de
        // longitud, los índices dejarían de estar alineados con `$chars` y el token ya no sería
        // el trozo que se leyó. Indexando por carácter, la correspondencia es 1:1 pase lo que pase.
        $lower = array_map(static fn (string $char): string => mb_strtolower($char, 'UTF-8'), $chars);

        $positions = $this->alphabet->positions();
        $maxLength = $this->alphabet->maxGraphemeLength();
        $total = count($chars);

        $tokens = [];
        $index = 0;

        while ($index < $total) {
            $matched = false;

            // De largo a corto: `ky’` antes que `ky`, y `ky` antes que `k`.
            for ($length = min($maxLength, $total - $index); $length >= 1; $length--) {
                $candidate = implode('', array_slice($lower, $index, $length));

                if (! isset($positions[$candidate])) {
                    continue;
                }

                if ($candidate === TextNormalizer::APOSTROPHE) {
                    $this->assertSaltilloIsLegitimate($tokens === [] ? null : $tokens[count($tokens) - 1]);
                }

                $tokens[] = new Grapheme(
                    implode('', array_slice($chars, $index, $length)),
                    $positions[$candidate],
                    $this->alphabet->typeOf($candidate),
                );

                $index += $length;
                $matched = true;
                break;
            }

            if ($matched) {
                continue;
            }

            // Fuera del alfabeto: el espacio de un lema de dos palabras, la puntuación de una
            // oración de ejemplo, una letra del castellano en un préstamo. Se conserva con
            // posición nula en lugar de lanzar, para que el mutator no reviente a mitad de un
            // guardado; `unknownCharacters()` los deja a la vista de quien quiera avisar.
            $tokens[] = new Grapheme($chars[$index], null, null);
            $index++;
        }

        return $tokens;
    }

    /**
     * Caracteres fuera del alfabeto, únicos y en orden de aparición.
     *
     * @return list<string>
     */
    public function unknownCharacters(string $text): array
    {
        $unknown = [];

        foreach ($this->tokenize($text) as $grapheme) {
            if (! $grapheme->isKnown() && ! in_array($grapheme->text, $unknown, true)) {
                $unknown[] = $grapheme->text;
            }
        }

        return $unknown;
    }

    /**
     * La regla del apóstrofo, que es la parte que se equivoca siempre: el apóstrofo cumple dos
     * funciones distintas según lo que lo preceda. Tras consonante forma parte de la glotalizada
     * y es inseparable de ella (`b’`, `tz’`, `ky’`); tras vocal es el saltillo, grafema autónomo
     * de la última posición (`jwe’`, `a’`, `pa’ch`).
     *
     * La coincidencia más larga primero ya implementa la primera mitad: toda glotalizada legítima
     * está en el catálogo, así que `q’` se consume entera antes de que el apóstrofo se mire solo.
     * Las dos formulaciones coinciden por construcción, y esta comprobación es el pasador: si el
     * grafema anterior es una consonante que sí tiene glotalizada en el catálogo, la coincidencia
     * más larga falló y la segmentación está mal. Es un error de programa, no de datos.
     *
     * `s’` no viola nada: las glotalizadas son un conjunto cerrado y `s` no está en él. Ahí el
     * apóstrofo es saltillo y se sigue, que el corpus viene de PDF y trae de todo.
     */
    private function assertSaltilloIsLegitimate(?Grapheme $previous): void
    {
        if ($previous === null) {
            return;
        }

        $before = mb_strtolower($previous->text, 'UTF-8');

        if (! $this->alphabet->isConsonant($before)) {
            return;
        }

        $glottalized = $before.TextNormalizer::APOSTROPHE;

        if (isset($this->alphabet->positions()[$glottalized])) {
            throw new LogicException(sprintf(
                'El apóstrofo tras «%s» debió absorberse en la glotalizada «%s», que está en el '.
                'catálogo. La coincidencia más larga primero falló y la segmentación es incorrecta.',
                $previous->text,
                $glottalized,
            ));
        }
    }
}
