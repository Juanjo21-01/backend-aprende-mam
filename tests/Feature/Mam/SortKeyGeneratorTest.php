<?php

declare(strict_types=1);

namespace Tests\Feature\Mam;

use App\Support\Mam\Alphabet;
use App\Support\Mam\SortKeyGenerator;
use App\Support\Mam\Tokenizer;
use Database\Seeders\GrafemasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Vectores de `.claude/rules/ortografia-mam.md`, secciones «Clave de ordenamiento» y
 * «Ordenamiento».
 *
 * Esto es lo que alimenta la columna `orden_alfabetico`. El orden alfabético del Mam no es el del
 * castellano y el cotejamiento de MySQL no lo sabe: `chmol` va antes que `ch’el`, y `xaq` antes
 * que `ẍiky` —para `utf8mb4_unicode_ci` `x` y `ẍ` son la misma letra—. De ahí la regla del
 * proyecto de no usar nunca `ORDER BY mam`.
 */
final class SortKeyGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GrafemasSeeder::class);
    }

    /**
     * Los dos vectores del archivo de reglas.
     *
     * @return array<string, array{string, string}>
     */
    public static function vectoresDeClave(): array
    {
        return [
            'tz’is' => ['tz’is', '260620'],     // [tz’][i][s] → 26 06 20
            'chmol' => ['chmol', '03131512'],   // [ch][m][o][l] → 03 13 15 12
        ];
    }

    /**
     * El vector de ordenamiento del archivo de reglas: entrada desordenada => orden esperado.
     *
     * @return array{list<string>, list<string>}
     */
    public static function vectorDeOrdenamiento(): array
    {
        return [
            ['chmol', 'b’aq', 'tz’is', 'jal', 'ch’el', 'kyaje', 'tzaj', 'k’um', 'ẍiky', 'xaq'],
            ['b’aq', 'chmol', 'ch’el', 'jal', 'k’um', 'kyaje', 'tzaj', 'tz’is', 'xaq', 'ẍiky'],
        ];
    }

    /** Guardián del canon; mismo patrón que `GrafemasSeeder::assertSourceIntegrity()`. */
    public function test_los_vectores_usan_el_apostrofo_canonico(): void
    {
        [$desordenadas, $ordenadas] = self::vectorDeOrdenamiento();

        $texto = implode('', array_merge(
            $desordenadas,
            $ordenadas,
            array_map(fn (array $v): string => $v[0], array_values(self::vectoresDeClave())),
        ));

        foreach (["'", "\u{02BC}", "\u{A78C}"] as $apostrofo) {
            $this->assertStringNotContainsString(
                $apostrofo,
                $texto,
                'Un vector de este archivo trae un apóstrofo no canónico; el único válido es U+2019.'
            );
        }

        foreach (['õ', 'Õ'] as $corrupcion) {
            $this->assertStringNotContainsString(
                $corrupcion,
                $texto,
                "Un vector de este archivo trae `{$corrupcion}`, que no existe en el alfabeto Mam."
            );
        }
    }

    #[DataProvider('vectoresDeClave')]
    public function test_genera_la_clave_de_ordenamiento(string $palabra, string $esperada): void
    {
        $this->assertSame($esperada, $this->generator()->generate($palabra));
    }

    /** El mutator ya tokenizó para otras cosas; no hace falta volver a hacerlo. */
    #[DataProvider('vectoresDeClave')]
    public function test_acepta_grafemas_ya_tokenizados(string $palabra, string $esperada): void
    {
        $grafemas = $this->app->make(Tokenizer::class)->tokenize($palabra);

        $this->assertSame($esperada, $this->generator()->fromGraphemes($grafemas));
    }

    /**
     * El vector completo del archivo de reglas. Cubre los dos casos donde el orden del Mam se
     * separa del castellano: `chmol` antes que `ch’el`, y `xaq` antes que `ẍiky`.
     */
    public function test_ordena_segun_el_alfabeto_mam(): void
    {
        [$desordenadas, $esperadas] = self::vectorDeOrdenamiento();

        $claves = [];

        foreach ($desordenadas as $palabra) {
            $claves[$palabra] = $this->generator()->generate($palabra);
        }

        asort($claves, SORT_STRING);

        $this->assertSame(
            $esperadas,
            array_keys($claves),
            PHP_EOL.'Claves generadas: '.json_encode($claves, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** Una palabra que es prefijo de otra ordena antes. */
    public function test_el_prefijo_ordena_antes_que_la_palabra_larga(): void
    {
        $jal = $this->generator()->generate('jal');
        $jala = $this->generator()->generate('jala');

        $this->assertSame('070112', $jal);
        $this->assertSame('07011201', $jala);
        $this->assertLessThan(0, strcmp($jal, $jala));
    }

    /**
     * Los caracteres fuera del alfabeto (espacio en un lema de dos palabras, guion) se codifican
     * como `00`, que ordena antes que `a` = 01. Es la convención de diccionario: «ja la» antes
     * que «jala».
     */
    public function test_los_caracteres_fuera_del_alfabeto_ordenan_primero(): void
    {
        $conEspacio = $this->generator()->generate('ja la');
        $sinEspacio = $this->generator()->generate('jala');

        $this->assertSame('0701001201', $conEspacio);
        $this->assertLessThan(0, strcmp($conEspacio, $sinEspacio));
    }

    public function test_la_clave_no_depende_de_la_caja(): void
    {
        $this->assertSame(
            $this->generator()->generate('tz’is'),
            $this->generator()->generate('Tz’is')
        );
    }

    public function test_la_cadena_vacia_produce_clave_vacia(): void
    {
        $this->assertSame('', $this->generator()->generate(''));
    }

    /**
     * `orden_alfabetico` va a una columna `utf8mb4_unicode_ci`, el mismo cotejamiento que no
     * distingue `x` de `ẍ`. La clave ordena bien ahí porque es ASCII de dígitos y nada más.
     */
    public function test_la_clave_es_solo_digitos(): void
    {
        foreach (self::vectorDeOrdenamiento()[0] as $palabra) {
            $clave = $this->generator()->generate($palabra);

            $this->assertMatchesRegularExpression('/^\d+$/', $clave);
            $this->assertSame(0, mb_strlen($clave) % 2, "«{$palabra}» produjo una clave de largo impar: {$clave}");
        }
    }

    /**
     * La propiedad que justifica que el alfabeto sea catálogo en base y no constantes: si COLIMAM
     * ratifica un cambio de orden, basta re-sembrar. Aquí se intercambian `x` (29) y `ẍ` (30) y
     * el orden del resultado se da vuelta sin tocar una línea de código.
     */
    public function test_el_orden_sale_de_la_base_y_no_de_constantes(): void
    {
        $this->assertLessThan(
            0,
            strcmp($this->generator()->generate('xaq'), $this->generator()->generate('ẍiky')),
            'Con el catálogo sembrado, `xaq` (29) va antes que `ẍiky` (30).'
        );

        // `posicion` es única: hace falta un valor de paso para intercambiarlas.
        DB::table('grafemas')->where('posicion', 29)->update(['posicion' => 99]);
        DB::table('grafemas')->where('posicion', 30)->update(['posicion' => 29]);
        DB::table('grafemas')->where('posicion', 99)->update(['posicion' => 30]);

        $this->app->make(Alphabet::class)->forget();

        $this->assertLessThan(
            0,
            strcmp($this->generator()->generate('ẍiky'), $this->generator()->generate('xaq')),
            'Con las posiciones intercambiadas en la base, el orden debe darse vuelta.'
        );
    }

    private function generator(): SortKeyGenerator
    {
        return $this->app->make(SortKeyGenerator::class);
    }
}
