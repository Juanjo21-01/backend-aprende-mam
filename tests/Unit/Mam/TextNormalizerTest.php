<?php

declare(strict_types=1);

namespace Tests\Unit\Mam;

use App\Support\Mam\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Vectores de `.claude/rules/ortografia-mam.md`, secciones «Normalización» y «Clave de búsqueda».
 *
 * Sin base de datos a propósito: el normalizador es una función pura y no depende del catálogo.
 *
 * OJO al leer este archivo: el archivo de reglas está escrito con el apóstrofo recto U+0027 de
 * principio a fin —no contiene ni un solo U+2019—, así que el vector «"q'aq'" con U+0027 →
 * "q'aq'" con U+2019» es una tautología allá. Aquí los lados canónicos van con U+2019 de verdad
 * y `test_los_vectores_canonicos_no_traen_corrupcion()` lo vigila. Este archivo es el canon
 * ejecutable; el de reglas es la prosa.
 */
final class TextNormalizerTest extends TestCase
{
    /** U+2019, el único apóstrofo válido en el proyecto. */
    private const APOSTROFO = '’';

    /** U+1E8D, `x` con diéresis. La que los PDF de origen entregan como `õ`. */
    private const X_DIERESIS = 'ẍ';

    /**
     * Los tres apóstrofos que hay que convertir a U+2019: el recto del teclado, la letra
     * modificadora y el saltillo latino. En pantalla los cuatro son la misma rayita.
     *
     * @return list<string>
     */
    private static function apostrofosNoCanonicos(): array
    {
        return ["'", "\u{02BC}", "\u{A78C}"];
    }

    /**
     * Entrada cruda => salida canónica esperada.
     *
     * @return array<string, array{string, string}>
     */
    public static function vectoresDeNormalizacion(): array
    {
        return [
            // Los tres apóstrofos no canónicos del corpus.
            'apóstrofo recto U+0027' => ["q'aq'", 'q’aq’'],
            'letra modificadora U+02BC' => ["q\u{02BC}aq\u{02BC}", 'q’aq’'],
            'saltillo latino U+A78C' => ["q\u{A78C}aq\u{A78C}", 'q’aq’'],

            // La corrupción tipográfica de los PDF: `õ` no existe en el alfabeto Mam.
            'õ precompuesta' => ['õiky', 'ẍiky'],
            'Õ precompuesta conserva la caja' => ['MOÕ', 'MOẌ'],

            // Composición: el corpus también trae la diéresis suelta.
            'x + U+0308 se compone' => ["x\u{0308}", 'ẍ'],
            'x + U+0308 en palabra' => ["x\u{0308}iky", 'ẍiky'],

            // El archivo de reglas manda `õ`→`ẍ` antes que NFC. Si la corrupción llega
            // descompuesta (`o` + U+0303), ese orden la deja pasar: la sustitución no la ve y
            // el NFC posterior la recompone a `õ`. Por eso NFC va primero.
            'o + U+0303 descompuesta también es corrupción' => ["o\u{0303}iky", 'ẍiky'],
            'O + U+0303 descompuesta conserva la caja' => ["MO\u{0303}", 'MẌ'],

            // Casos de borde.
            'texto ya canónico no cambia' => ['tx’otx’', 'tx’otx’'],
            'cadena vacía' => ['', ''],
            'texto sin nada que normalizar' => ['chmol', 'chmol'],
        ];
    }

    /**
     * Entrada => clave de búsqueda esperada. Minúsculas, sin saltillos, `ẍ`→`x`,
     * sin diacríticos del castellano.
     *
     * @return array<string, array{string, string}>
     */
    public static function vectoresDeBusqueda(): array
    {
        return [
            // Los seis vectores del archivo de reglas, con el apóstrofo ya canónico.
            'q’aq’' => ['q’aq’', 'qaq'],
            'ẍiky' => ['ẍiky', 'xiky'],
            'tx’otx’' => ['tx’otx’', 'txotx'],
            'kyiẍ' => ['kyiẍ', 'kyix'],
            'Xq’exwi’' => ['Xq’exwi’', 'xqexwi'],
            'b’ib’itz' => ['b’ib’itz', 'bibitz'],

            // Los mismos, tal como vienen del PDF: apóstrofo recto y `õ`.
            'q’aq’ con U+0027' => ["q'aq'", 'qaq'],
            'Xq’exwi’ con U+0027' => ["Xq'exwi'", 'xqexwi'],
            'õiky corrupta' => ['õiky', 'xiky'],
            'MOÕ corrupta y en mayúsculas' => ['MOÕ', 'mox'],

            // La columna `busqueda` también recibe texto en castellano de los ejemplos.
            'diacrítico del castellano' => ['pájaro', 'pajaro'],
            'diéresis del castellano' => ['pingüino', 'pinguino'],

            'cadena vacía' => ['', ''],
        ];
    }

    /**
     * Guardián del canon. Si este archivo se edita en un editor que «arregla» las comillas, o si
     * `ẍ` se degrada a `õ` al copiar y pegar, los vectores dejarían de probar lo que dicen probar
     * y todo lo demás pasaría en verde. Mismo patrón que `GrafemasSeeder::assertSourceIntegrity()`.
     */
    public function test_los_vectores_canonicos_no_traen_corrupcion(): void
    {
        $canonicos = array_merge(
            array_map(fn (array $v): string => $v[1], array_values(self::vectoresDeNormalizacion())),
            array_map(fn (array $v): string => $v[1], array_values(self::vectoresDeBusqueda())),
        );

        foreach ($canonicos as $esperado) {
            foreach (self::apostrofosNoCanonicos() as $apostrofo) {
                $this->assertStringNotContainsString(
                    $apostrofo,
                    $esperado,
                    "El vector «{$esperado}» trae un apóstrofo no canónico (".self::codepoints($apostrofo).'); '.
                    'el único válido es U+2019.'
                );
            }

            foreach (['õ', 'Õ'] as $corrupcion) {
                $this->assertStringNotContainsString(
                    $corrupcion,
                    $esperado,
                    "El vector «{$esperado}» trae `{$corrupcion}`, que no existe en el alfabeto Mam."
                );
            }
        }

        // Y que las constantes de este archivo sean lo que dicen ser.
        $this->assertSame("\u{2019}", self::APOSTROFO);
        $this->assertSame("\u{1E8D}", self::X_DIERESIS);
    }

    #[DataProvider('vectoresDeNormalizacion')]
    public function test_normaliza(string $entrada, string $esperado): void
    {
        $this->assertSameTexto($esperado, TextNormalizer::normalize($entrada));
    }

    /**
     * El normalizador corre en el mutator, así que se aplica también sobre texto que ya pasó por
     * él (una edición desde el panel, un re-guardado del importador). Debe ser un punto fijo.
     */
    #[DataProvider('vectoresDeNormalizacion')]
    public function test_la_normalizacion_es_idempotente(string $entrada, string $esperado): void
    {
        $unaVez = TextNormalizer::normalize($entrada);

        $this->assertSameTexto($unaVez, TextNormalizer::normalize($unaVez));
        $this->assertSameTexto($esperado, TextNormalizer::normalize($unaVez));
    }

    #[DataProvider('vectoresDeBusqueda')]
    public function test_genera_la_clave_de_busqueda(string $entrada, string $esperado): void
    {
        $this->assertSameTexto($esperado, TextNormalizer::searchKey($entrada));
    }

    /** La clave de búsqueda es lo que teclea un estudiante: ASCII y nada más. */
    #[DataProvider('vectoresDeBusqueda')]
    public function test_la_clave_de_busqueda_no_conserva_saltillos_ni_dieresis(string $entrada, string $esperado): void
    {
        $clave = TextNormalizer::searchKey($entrada);

        $this->assertSameTexto($esperado, $clave);
        $this->assertStringNotContainsString(self::APOSTROFO, $clave);
        $this->assertStringNotContainsString(self::X_DIERESIS, $clave);
        $this->assertSame($clave, mb_strtolower($clave, 'UTF-8'));
    }

    /**
     * `assertSame` a secas reporta «esperado ẍ, obtenido ẍ» cuando uno es U+1E8D y el otro
     * `x`+U+0308. Los puntos de código son la única forma de ver la diferencia.
     */
    private function assertSameTexto(string $esperado, string $obtenido): void
    {
        $this->assertSame(
            $esperado,
            $obtenido,
            PHP_EOL.
            'Esperado: '.self::codepoints($esperado).PHP_EOL.
            'Obtenido: '.self::codepoints($obtenido)
        );
    }

    private static function codepoints(string $valor): string
    {
        if ($valor === '') {
            return '(cadena vacía)';
        }

        $partes = [];

        foreach (preg_split('//u', $valor, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $partes[] = sprintf('U+%04X', mb_ord($char, 'UTF-8'));
        }

        return implode(' ', $partes);
    }
}
