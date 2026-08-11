<?php

declare(strict_types=1);

namespace App\Support\Mam;

/**
 * Un grafema del alfabeto Mam tal como salió del tokenizador.
 *
 * `text` conserva el trozo original con su caja (`Tx’` si el lema venía capitalizado); `position`
 * es la del catálogo `grafemas` y es lo único que importa para ordenar. Un carácter fuera del
 * alfabeto —el espacio de un lema de dos palabras, la puntuación de una oración de ejemplo— llega
 * aquí con `position` y `type` en `null`: el tokenizador no lanza, para que el mutator no reviente
 * a mitad de un guardado.
 */
final readonly class Grapheme
{
    public function __construct(
        public string $text,
        public ?int $position,
        public ?string $type,
    ) {}

    public function isKnown(): bool
    {
        return $this->position !== null;
    }
}
