<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Clases de palabra usadas por ALMG, extraídas por frecuencias del diccionario COLIMAM.
 *
 * Fuente: `.claude/rules/ortografia-mam.md`. Las abreviaturas son las que el propio
 * diccionario imprime tras el separador `||`, así que son también la clave por la que el
 * futuro importador del corpus va a resolver la clase de cada entrada.
 *
 * `pos.`, `afect.`, `dir.` y `clas.` son clases propias de las lenguas mayas y no tienen
 * equivalente en la gramática del castellano. Se conservan tal cual; mapearlas a las
 * categorías del español sería lingüísticamente incorrecto.
 *
 * Las descripciones son un punto de partida para que el editor sepa qué está eligiendo;
 * el texto definitivo de las cuatro clases mayas corresponde ratificarlo a la validación
 * lingüística, igual que el orden del alfabeto.
 */
class CategoriasGramaticalesSeeder extends Seeder
{
    /**
     * `dir.`, `dem.` y `nom.` aparecen agrupadas en la tabla de frecuencias del corpus por
     * ser las menos numerosas, pero son tres clases distintas y aquí van separadas.
     *
     * @var list<array{string, string, string|null}> abreviatura, nombre, descripcion
     */
    private const CLASES = [
        ['s.', 'Sustantivo', null],
        ['v.t.', 'Verbo transitivo', null],
        ['v.i.', 'Verbo intransitivo', null],
        ['adj.', 'Adjetivo', null],
        ['adv.', 'Adverbio', null],
        ['pron.', 'Pronombre', null],
        ['num.', 'Numeral', null],
        ['part.', 'Partícula', null],
        ['af.', 'Afijo', null],
        ['med.', 'Medida', 'Palabra que expresa una unidad de medida tradicional.'],
        ['dem.', 'Demostrativo', null],
        ['nom.', 'Nominal', null],
        [
            'pos.',
            'Posicional',
            'Clase propia de las lenguas mayas: describe la posición, la forma o la '.
            'disposición física de algo. Sin equivalente directo en castellano.',
        ],
        [
            'afect.',
            'Afectivo',
            'Clase propia de las lenguas mayas: expresa sonidos, movimientos o '.
            'impresiones sensoriales. Sin equivalente directo en castellano.',
        ],
        [
            'clas.',
            'Clasificador',
            'Clase propia de las lenguas mayas: acompaña al numeral según la naturaleza '.
            'de lo que se cuenta. Sin equivalente directo en castellano.',
        ],
        [
            'dir.',
            'Direccional',
            'Clase propia de las lenguas mayas: marca la dirección del movimiento que '.
            'acompaña al verbo. Sin equivalente directo en castellano.',
        ],
    ];

    public function run(): void
    {
        $ahora = now();

        $filas = array_map(fn (array $clase): array => [
            'abreviatura' => $clase[0],
            'nombre' => $clase[1],
            'descripcion' => $clase[2],
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], self::CLASES);

        // Idempotente: `abreviatura` es única, así que re-sembrar corrige el catálogo en
        // lugar de duplicarlo, y no rompe las entradas que ya apuntan a estas filas.
        DB::table('categorias_gramaticales')->upsert(
            $filas,
            ['abreviatura'],
            ['nombre', 'descripcion', 'updated_at']
        );
    }
}
