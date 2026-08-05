<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Los 32 grafemas del alfabeto Mam en orden de intercalación.
 *
 * Fuente: `.claude/rules/ortografia-mam.md`. El orden NO es el del castellano:
 * `ch` va antes que `ch'`, `x` antes que `ẍ`, y el saltillo cierra el alfabeto.
 *
 * Dos caracteres de este archivo se corrompen sin dejar rastro visible:
 *   - `ẍ` es U+1E8D (x con diéresis). Si aparece `õ` (U+00F5) el archivo está roto:
 *     `õ` no existe en el alfabeto Mam.
 *   - El saltillo es U+2019 (comilla tipográfica derecha), NO el U+0027 del teclado.
 * `assertSourceIntegrity()` lo comprueba antes de escribir nada.
 */
class GrafemasSeeder extends Seeder
{
    private const SALTILLO = '’'; // U+2019

    private const X_DIERESIS = 'ẍ'; // U+1E8D

    /**
     * @var list<array{int, string, string, bool}>  posicion, grafema, tipo, es_digrafo
     */
    private const GRAFEMAS = [
        [1,  'a',            'vocal',                false],
        [2,  'b’',           'glotalizada',          false],
        [3,  'ch',           'consonante_compuesta', true],
        [4,  'ch’',          'glotalizada',          false],
        [5,  'e',            'vocal',                false],
        [6,  'i',            'vocal',                false],
        [7,  'j',            'consonante_simple',    false],
        [8,  'k',            'consonante_simple',    false],
        [9,  'k’',           'glotalizada',          false],
        [10, 'ky',           'consonante_compuesta', true],
        [11, 'ky’',          'glotalizada',          false],
        [12, 'l',            'consonante_simple',    false],
        [13, 'm',            'consonante_simple',    false],
        [14, 'n',            'consonante_simple',    false],
        [15, 'o',            'vocal',                false],
        [16, 'p',            'consonante_simple',    false],
        [17, 'q',            'consonante_simple',    false],
        [18, 'q’',           'glotalizada',          false],
        [19, 'r',            'consonante_simple',    false],
        [20, 's',            'consonante_simple',    false],
        [21, 't',            'consonante_simple',    false],
        [22, 't’',           'glotalizada',          false],
        [23, 'tx',           'consonante_compuesta', true],
        [24, 'tx’',          'glotalizada',          false],
        [25, 'tz',           'consonante_compuesta', true],
        [26, 'tz’',          'glotalizada',          false],
        [27, 'u',            'vocal',                false],
        [28, 'w',            'consonante_simple',    false],
        [29, 'x',            'consonante_simple',    false],
        [30, 'ẍ',            'consonante_simple',    false],
        [31, 'y',            'consonante_simple',    false],
        [32, '’',            'saltillo',             false],
    ];

    public function run(): void
    {
        $this->assertSourceIntegrity();

        $filas = array_map(fn (array $g) => [
            'posicion' => $g[0],
            'grafema' => $g[1],
            'tipo' => $g[2],
            'es_digrafo' => $g[3],
        ], self::GRAFEMAS);

        // Idempotente: `posicion` es única, así que re-sembrar corrige el catálogo
        // en lugar de duplicarlo.
        DB::table('grafemas')->upsert($filas, ['posicion'], ['grafema', 'tipo', 'es_digrafo']);
    }

    /**
     * El alfabeto es el cimiento de la ortografía: si este archivo llega corrupto,
     * todo lo derivado (tokenización, `orden_alfabetico`, `busqueda`) sale mal en
     * silencio. Preferible reventar aquí.
     */
    private function assertSourceIntegrity(): void
    {
        $grafemas = array_column(self::GRAFEMAS, 1);
        $juntos = implode('', $grafemas);

        if (count(self::GRAFEMAS) !== 32) {
            throw new RuntimeException('El alfabeto Mam tiene 32 grafemas; el catálogo trae '.count(self::GRAFEMAS).'.');
        }

        if (array_column(self::GRAFEMAS, 0) !== range(1, 32)) {
            throw new RuntimeException('Las posiciones deben ir de 1 a 32 sin huecos ni repeticiones.');
        }

        if (! mb_check_encoding($juntos, 'UTF-8')) {
            throw new RuntimeException('El catálogo de grafemas no es UTF-8 válido.');
        }

        if (str_contains($juntos, 'õ') || str_contains($juntos, 'Õ')) {
            throw new RuntimeException('`õ` no existe en el alfabeto Mam: el archivo llegó con la corrupción de los PDF de origen.');
        }

        if (! in_array(self::X_DIERESIS, $grafemas, true)) {
            throw new RuntimeException('Falta `ẍ` (U+1E8D) en la posición 30.');
        }

        if (! in_array(self::SALTILLO, $grafemas, true)) {
            throw new RuntimeException('Falta el saltillo `’` (U+2019) en la posición 32.');
        }

        foreach (["'", "\u{02BC}", "\u{A78C}"] as $apostrofoNoCanonico) {
            if (str_contains($juntos, $apostrofoNoCanonico)) {
                throw new RuntimeException(
                    'El catálogo trae un apóstrofo no canónico (U+'.
                    strtoupper(dechex(mb_ord($apostrofoNoCanonico, 'UTF-8'))).
                    '); el único válido es U+2019.'
                );
            }
        }
    }
}
