<?php

declare(strict_types=1);

namespace App\Support\Mam;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El alfabeto Mam leído de la tabla `grafemas`.
 *
 * El catálogo vive en base y no en constantes para que un ajuste ratificado por COLIMAM no
 * obligue a tocar la implementación: se re-siembra y listo. Ni el inventario de grafemas ni el
 * orden de intercalación ni la longitud máxima están cableados aquí.
 *
 * Se registra como singleton en `AppServiceProvider`: una consulta por proceso, sin caché ni
 * invalidación que mantener. Una importación de 6,185 entradas no puede hacer una consulta por
 * palabra.
 *
 * La indexación se hace en PHP y no con `where('grafema', ...)` a propósito: `utf8mb4_unicode_ci`
 * no distingue `x` de `ẍ`, así que para MySQL las posiciones 29 y 30 son la misma cadena.
 */
final class Alphabet
{
    /** @var array<string, int>|null grafema en minúsculas => posición */
    private ?array $positions = null;

    /** @var array<string, string> grafema en minúsculas => tipo */
    private array $types = [];

    private int $maxGraphemeLength = 0;

    private int $saltilloPosition = 0;

    /** @return array<string, int> */
    public function positions(): array
    {
        $this->load();

        return $this->positions ?? [];
    }

    /** Longitud del grafema más largo, en caracteres. Con el catálogo actual, 3 (`tz’`). */
    public function maxGraphemeLength(): int
    {
        $this->load();

        return $this->maxGraphemeLength;
    }

    /** Posición del saltillo; cierra el alfabeto. Con el catálogo actual, 32. */
    public function saltilloPosition(): int
    {
        $this->load();

        return $this->saltilloPosition;
    }

    public function typeOf(string $lowercasedGrapheme): ?string
    {
        $this->load();

        return $this->types[$lowercasedGrapheme] ?? null;
    }

    public function isVowel(string $lowercasedGrapheme): bool
    {
        return $this->typeOf($lowercasedGrapheme) === 'vocal';
    }

    /** Las glotalizadas cuentan: lo que importa para la regla del apóstrofo es no ser vocal. */
    public function isConsonant(string $lowercasedGrapheme): bool
    {
        return in_array(
            $this->typeOf($lowercasedGrapheme),
            ['consonante_simple', 'consonante_compuesta', 'glotalizada'],
            true
        );
    }

    /** Descarta lo memorizado. Lo usan los tests que mutan el catálogo. */
    public function forget(): void
    {
        $this->positions = null;
        $this->types = [];
        $this->maxGraphemeLength = 0;
        $this->saltilloPosition = 0;
    }

    private function load(): void
    {
        if ($this->positions !== null) {
            return;
        }

        $rows = DB::table('grafemas')
            ->orderBy('posicion')
            ->get(['posicion', 'grafema', 'tipo']);

        if ($rows->isEmpty()) {
            throw new RuntimeException(
                'El catálogo `grafemas` está vacío: sin alfabeto no hay tokenización ni orden. '.
                'Correr `php artisan db:seed --class=GrafemasSeeder`.'
            );
        }

        $positions = [];
        $types = [];
        $maxLength = 0;
        $saltillo = null;

        foreach ($rows as $row) {
            // Normalizado y en minúsculas: la clave del índice tiene que coincidir con lo que
            // produce el tokenizador. Si alguien sembrara `b'` con U+0027, esto lo salva.
            $key = mb_strtolower(TextNormalizer::normalize((string) $row->grafema), 'UTF-8');

            $positions[$key] = (int) $row->posicion;
            $types[$key] = (string) $row->tipo;
            $maxLength = max($maxLength, mb_strlen($key));

            if ($row->tipo === 'saltillo') {
                $saltillo = (int) $row->posicion;
            }
        }

        if ($saltillo === null) {
            throw new RuntimeException(
                'El catálogo `grafemas` no tiene ninguna fila de tipo `saltillo`. Sin ella el '.
                'apóstrofo tras vocal no tiene posición y el orden alfabético sale mal. '.
                'Correr `php artisan db:seed --class=GrafemasSeeder`.'
            );
        }

        $this->positions = $positions;
        $this->types = $types;
        $this->maxGraphemeLength = $maxLength;
        $this->saltilloPosition = $saltillo;
    }
}
