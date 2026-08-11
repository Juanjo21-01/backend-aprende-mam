<?php

declare(strict_types=1);

namespace Tests\Feature\Mam;

use App\Support\Mam\Alphabet;
use App\Support\Mam\Grapheme;
use App\Support\Mam\Tokenizer;
use Database\Seeders\GrafemasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Vectores de `.claude/rules/ortografia-mam.md`, sección «Tokenización».
 *
 * Va en Feature y no en Unit porque el alfabeto vive en la tabla `grafemas`: el tokenizador lee
 * el orden y el inventario de la base, no de constantes, para que un ajuste ratificado por
 * COLIMAM no obligue a tocar código. `test_lee_el_alfabeto_de_la_base_y_no_de_constantes()`
 * es el que verifica esa propiedad.
 *
 * El archivo de reglas escribe sus vectores con el apóstrofo recto U+0027; aquí van con U+2019,
 * que es lo que guarda el catálogo, y `test_los_vectores_usan_el_apostrofo_canonico()` lo vigila.
 */
final class TokenizerTest extends TestCase
{
    use RefreshDatabase;

    private const APOSTROFO = '’'; // U+2019

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GrafemasSeeder::class);
    }

    /**
     * Los 12 vectores del archivo de reglas: palabra => grafemas esperados.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function vectoresDeTokenizacion(): array
    {
        return [
            'tz’is' => ['tz’is', ['tz’', 'i', 's']],
            'tx’otx’' => ['tx’otx’', ['tx’', 'o', 'tx’']],
            'ky’aq' => ['ky’aq', ['ky’', 'a', 'q']],
            'q’aq’' => ['q’aq’', ['q’', 'a', 'q’']],
            'b’ib’itz' => ['b’ib’itz', ['b’', 'i', 'b’', 'i', 'tz']],
            'kyiẍ' => ['kyiẍ', ['ky', 'i', 'ẍ']],
            'chmol' => ['chmol', ['ch', 'm', 'o', 'l']],
            'tx’ajol' => ['tx’ajol', ['tx’', 'a', 'j', 'o', 'l']],

            // Saltillo tras vocal: grafema autónomo, posición 32.
            'jwe’' => ['jwe’', ['j', 'w', 'e', '’']],
            'a’witz' => ['a’witz', ['a', '’', 'w', 'i', 'tz']],

            // Saltillo tras vocal y, pegado, un dígrafo.
            'pa’ch' => ['pa’ch', ['p', 'a', '’', 'ch']],

            // Las dos funciones del apóstrofo en la misma palabra: glotalizante en `q’`,
            // saltillo al final.
            'xq’exwi’' => ['xq’exwi’', ['x', 'q’', 'e', 'x', 'w', 'i', '’']],
        ];
    }

    /**
     * Guardián del canon: si un editor «arregla» las comillas de este archivo o `ẍ` se degrada a
     * `õ` al copiar y pegar, los vectores dejarían de probar lo que dicen y todo seguiría verde.
     * Mismo patrón que `GrafemasSeeder::assertSourceIntegrity()`.
     */
    public function test_los_vectores_usan_el_apostrofo_canonico(): void
    {
        $this->assertSame("\u{2019}", self::APOSTROFO);

        foreach (self::vectoresDeTokenizacion() as [$palabra, $grafemas]) {
            $texto = $palabra.implode('', $grafemas);

            foreach (["'", "\u{02BC}", "\u{A78C}"] as $apostrofo) {
                $this->assertStringNotContainsString(
                    $apostrofo,
                    $texto,
                    "El vector «{$palabra}» trae un apóstrofo no canónico; el único válido es U+2019."
                );
            }

            foreach (['õ', 'Õ'] as $corrupcion) {
                $this->assertStringNotContainsString(
                    $corrupcion,
                    $texto,
                    "El vector «{$palabra}» trae `{$corrupcion}`, que no existe en el alfabeto Mam."
                );
            }
        }
    }

    /**
     * @param  list<string>  $esperados
     */
    #[DataProvider('vectoresDeTokenizacion')]
    public function test_tokeniza(string $palabra, array $esperados): void
    {
        $this->assertSameGrafemas($esperados, $this->tokenizer()->tokenize($palabra));
    }

    /**
     * El corpus llega con U+0027 —el archivo de reglas mismo está escrito así— y el catálogo
     * guarda U+2019. Si el tokenizador no normalizara su entrada, `tz'is` con apóstrofo recto se
     * partiría en `[t] [z] ['] [i] [s]`: cinco grafemas mal en lugar de tres, sin ningún error.
     *
     * @param  list<string>  $esperados
     */
    #[DataProvider('vectoresDeTokenizacion')]
    public function test_tokeniza_igual_con_apostrofo_recto(string $palabra, array $esperados): void
    {
        // Sustituir el apóstrofo entero es seguro; lo que nunca se puede hacer con `str_replace`
        // sobre texto en Mam es tocar una letra suelta de un dígrafo.
        $cruda = str_replace(self::APOSTROFO, "'", $palabra);

        $this->assertSameGrafemas($esperados, $this->tokenizer()->tokenize($cruda));
    }

    /** Lo que de verdad consume el generador de clave: las posiciones, no el texto. */
    public function test_devuelve_las_posiciones_del_catalogo(): void
    {
        $tokens = $this->tokenizer()->tokenize('tz’is');

        $this->assertSame([26, 6, 20], array_map(fn (Grapheme $g) => $g->position, $tokens));
        $this->assertSame(
            ['glotalizada', 'vocal', 'consonante_simple'],
            array_map(fn (Grapheme $g) => $g->type, $tokens)
        );
    }

    /** Los lemas del COLIMAM vienen capitalizados; la posición no depende de la caja. */
    public function test_conserva_la_caja_pero_reconoce_el_grafema(): void
    {
        $tokens = $this->tokenizer()->tokenize('Tx’otx’');

        $this->assertSame(['Tx’', 'o', 'tx’'], array_map(fn (Grapheme $g) => $g->text, $tokens));
        $this->assertSame([24, 15, 24], array_map(fn (Grapheme $g) => $g->position, $tokens));
    }

    /** `Ẍ` es U+1E8C, fuera de Latin-1, y la `ky` final es un solo grafema, no dos letras. */
    public function test_reconoce_la_x_con_dieresis_en_mayuscula(): void
    {
        $tokens = $this->tokenizer()->tokenize('Ẍiky');

        $this->assertSame(['Ẍ', 'i', 'ky'], array_map(fn (Grapheme $g) => $g->text, $tokens));
        $this->assertSame([30, 6, 10], array_map(fn (Grapheme $g) => $g->position, $tokens));
    }

    /**
     * Hay lemas de dos palabras, y el normalizador corre también sobre oraciones enteras
     * (ejemplos, diálogos, trabalenguas). El tokenizador no puede reventar por un espacio.
     */
    public function test_los_caracteres_fuera_del_alfabeto_salen_sin_posicion(): void
    {
        $tokens = $this->tokenizer()->tokenize('ja la');

        $this->assertSame(['j', 'a', ' ', 'l', 'a'], array_map(fn (Grapheme $g) => $g->text, $tokens));
        $this->assertSame([7, 1, null, 12, 1], array_map(fn (Grapheme $g) => $g->position, $tokens));
        $this->assertFalse($tokens[2]->isKnown());
        $this->assertTrue($tokens[0]->isKnown());
    }

    /** Para que el FormRequest o el importador avisen sin que el mutator lance a mitad de un guardado. */
    public function test_lista_los_caracteres_fuera_del_alfabeto(): void
    {
        $this->assertSame([], $this->tokenizer()->unknownCharacters('tx’otx’'));
        $this->assertSame([' '], $this->tokenizer()->unknownCharacters('ja la'));

        // Únicos y en orden de aparición.
        $this->assertSame(
            [' ', '.'],
            $this->tokenizer()->unknownCharacters('ku’x tzaj. Ku’x tzaj.')
        );
    }

    public function test_la_cadena_vacia_no_produce_tokens(): void
    {
        $this->assertSame([], $this->tokenizer()->tokenize(''));
        $this->assertSame([], $this->tokenizer()->unknownCharacters(''));
    }

    /** Sin carácter previo no hay consonante que glotalizar: es el saltillo. */
    public function test_el_saltillo_inicial_es_grafema_autonomo(): void
    {
        $tokens = $this->tokenizer()->tokenize('’aq');

        $this->assertSame(['’', 'a', 'q'], array_map(fn (Grapheme $g) => $g->text, $tokens));
        $this->assertSame([32, 1, 17], array_map(fn (Grapheme $g) => $g->position, $tokens));
    }

    /**
     * Las glotalizadas son un conjunto cerrado: `b’ ch’ k’ ky’ q’ t’ tx’ tz’`. Un apóstrofo tras
     * una consonante que no tiene glotalizada en el catálogo (`s’`, `m’`) es una anomalía del
     * corpus, no un grafema. Se emite el saltillo y se sigue: el mutator no puede lanzar a mitad
     * de un guardado. `unknownCharacters()` no lo reporta porque el saltillo sí pertenece al
     * alfabeto; lo que no pertenece es la secuencia.
     */
    public function test_el_apostrofo_tras_consonante_sin_glotalizada_se_trata_como_saltillo(): void
    {
        $tokens = $this->tokenizer()->tokenize('s’aq');

        $this->assertSame(['s', '’', 'a', 'q'], array_map(fn (Grapheme $g) => $g->text, $tokens));
        $this->assertSame([20, 32, 1, 17], array_map(fn (Grapheme $g) => $g->position, $tokens));
    }

    /**
     * La propiedad que justifica que el alfabeto sea una tabla y no un `const`. Si el tokenizador
     * tuviera `ky’` cableado, quitarlo del catálogo no cambiaría nada y este test pasaría en falso.
     */
    public function test_lee_el_alfabeto_de_la_base_y_no_de_constantes(): void
    {
        $this->assertSame(['ky’', 'a', 'q'], array_map(
            fn (Grapheme $g) => $g->text,
            $this->tokenizer()->tokenize('ky’aq')
        ));

        DB::table('grafemas')->where('posicion', 11)->delete();
        $this->olvidarAlfabeto();

        // Sin `ky’` en el catálogo, `ky` gana por coincidencia más larga y el apóstrofo cae
        // al saltillo.
        $this->assertSame(['ky', '’', 'a', 'q'], array_map(
            fn (Grapheme $g) => $g->text,
            $this->tokenizer()->tokenize('ky’aq')
        ));
    }

    public function test_falla_con_mensaje_accionable_si_el_catalogo_esta_vacio(): void
    {
        DB::table('grafemas')->delete();
        $this->olvidarAlfabeto();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/GrafemasSeeder/');

        $this->tokenizer()->tokenize('chmol');
    }

    private function tokenizer(): Tokenizer
    {
        return $this->app->make(Tokenizer::class);
    }

    /** `Alphabet` memoriza el catálogo; los tests que lo mutan tienen que descartarlo. */
    private function olvidarAlfabeto(): void
    {
        $this->app->make(Alphabet::class)->forget();
    }

    /**
     * @param  list<string>  $esperados
     * @param  list<Grapheme>  $tokens
     */
    private function assertSameGrafemas(array $esperados, array $tokens): void
    {
        $obtenidos = array_map(fn (Grapheme $g) => $g->text, $tokens);

        $this->assertSame(
            $esperados,
            $obtenidos,
            PHP_EOL.
            'Esperado: '.self::describir($esperados).PHP_EOL.
            'Obtenido: '.self::describir($obtenidos)
        );
    }

    /**
     * En pantalla `[tz’]` y `[t][z][’]` se leen casi igual, y `ẍ` es indistinguible de `x`.
     * El mensaje de fallo lleva los puntos de código.
     *
     * @param  list<string>  $grafemas
     */
    private static function describir(array $grafemas): string
    {
        $partes = array_map(static function (string $grafema): string {
            $puntos = [];

            foreach (preg_split('//u', $grafema, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
                $puntos[] = sprintf('U+%04X', mb_ord($char, 'UTF-8'));
            }

            return '['.$grafema.' = '.implode(' ', $puntos).']';
        }, $grafemas);

        return $partes === [] ? '(sin grafemas)' : implode(' ', $partes);
    }
}
