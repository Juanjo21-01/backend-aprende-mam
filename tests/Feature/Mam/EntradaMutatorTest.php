<?php

declare(strict_types=1);

namespace Tests\Feature\Mam;

use App\Models\Entrada;
use Database\Seeders\GrafemasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El punto donde la ortografía deja de ser una biblioteca y se vuelve un dato guardado.
 *
 * `app/Support/Mam/` ya tiene sus propias pruebas; lo que se comprueba aquí es que **ningún
 * texto en Mam llegue a la base sin pasar por ahí**, venga del panel, de un seeder, del
 * futuro importador del COLIMAM o de tinker. Por eso la normalización vive en el mutator
 * del modelo y no en un FormRequest: un FormRequest solo cubre el primero de esos cuatro
 * caminos.
 *
 * Vectores de `.claude/rules/ortografia-mam.md`. Ese archivo está escrito con el apóstrofo
 * recto U+0027, así que al transcribirlos aquí hay que convertirlos a U+2019 a mano; el
 * primer test de este archivo es el guardián de que eso se hizo.
 */
final class EntradaMutatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sin catálogo no hay tokenización, y sin tokenización el mutator no puede derivar
        // `orden_alfabetico`.
        $this->seed(GrafemasSeeder::class);
    }

    /**
     * Sección «Normalización»: lo que entra corrupto y lo que debe quedar guardado.
     *
     * @return array<string, array{string, string}>
     */
    public static function vectoresDeNormalizacion(): array
    {
        return [
            'apóstrofo recto U+0027' => ["q'aq'", 'q’aq’'],
            'apóstrofo modificador U+02BC' => ["q\u{02BC}aq\u{02BC}", 'q’aq’'],
            'saltillo latino U+A78C' => ["q\u{A78C}aq\u{A78C}", 'q’aq’'],
            'ẍ corrupta como õ' => ['õiky', 'ẍiky'],
            'Ẍ corrupta como Õ' => ['MOÕ', 'MOẌ'],
            'ẍ descompuesta' => ["x\u{0308}iky", 'ẍiky'],
        ];
    }

    /**
     * Sección «Clave de búsqueda»: lo que teclea quien busca.
     *
     * @return array<string, array{string, string}>
     */
    public static function vectoresDeBusqueda(): array
    {
        return [
            'q’aq’' => ['q’aq’', 'qaq'],
            'ẍiky' => ['ẍiky', 'xiky'],
            'tx’otx’' => ['tx’otx’', 'txotx'],
            'kyiẍ' => ['kyiẍ', 'kyix'],
            'Xq’exwi’' => ['Xq’exwi’', 'xqexwi'],
            'b’ib’itz' => ['b’ib’itz', 'bibitz'],
        ];
    }

    /**
     * Sección «Clave de ordenamiento».
     *
     * @return array<string, array{string, string}>
     */
    public static function vectoresDeOrden(): array
    {
        return [
            'tz’is' => ['tz’is', '260620'],
            'chmol' => ['chmol', '03131512'],
        ];
    }

    /** El vector de ordenamiento completo: desordenado => orden del Mam. */
    public static function vectorDeOrdenamiento(): array
    {
        return [
            ['chmol', 'b’aq', 'tz’is', 'jal', 'ch’el', 'kyaje', 'tzaj', 'k’um', 'ẍiky', 'xaq'],
            ['b’aq', 'chmol', 'ch’el', 'jal', 'k’um', 'kyaje', 'tzaj', 'tz’is', 'xaq', 'ẍiky'],
        ];
    }

    /**
     * Guardián del canon, igual que en `SortKeyGeneratorTest`: los vectores *esperados* de
     * este archivo tienen que estar escritos con U+2019. Los de entrada no, que justamente
     * traen la corrupción a propósito.
     */
    public function test_los_resultados_esperados_usan_el_apostrofo_canonico(): void
    {
        $esperados = implode('', array_merge(
            array_column(array_values(self::vectoresDeNormalizacion()), 1),
            array_column(array_values(self::vectoresDeBusqueda()), 0),
            self::vectorDeOrdenamiento()[0],
            self::vectorDeOrdenamiento()[1],
        ));

        foreach (["'", "\u{02BC}", "\u{A78C}"] as $apostrofo) {
            $this->assertStringNotContainsString(
                $apostrofo,
                $esperados,
                'Un resultado esperado de este archivo trae un apóstrofo no canónico; el único válido es U+2019.'
            );
        }

        foreach (['õ', 'Õ'] as $corrupcion) {
            $this->assertStringNotContainsString(
                $corrupcion,
                $esperados,
                "Un resultado esperado trae `{$corrupcion}`, que no existe en el alfabeto Mam."
            );
        }
    }

    #[DataProvider('vectoresDeNormalizacion')]
    public function test_normaliza_el_lema_al_guardar(string $entrada, string $esperado): void
    {
        $modelo = Entrada::create(['mam' => $entrada, 'espanol' => 'prueba']);

        // Comparación contra lo que quedó en la base, no contra el modelo en memoria: lo
        // que importa es que la corrupción no sobreviva el viaje.
        $this->assertSame($esperado, DB::table('entradas')->where('id', $modelo->id)->value('mam'));
    }

    #[DataProvider('vectoresDeBusqueda')]
    public function test_deriva_la_clave_de_busqueda(string $lema, string $esperada): void
    {
        $modelo = Entrada::create(['mam' => $lema, 'espanol' => 'prueba']);

        $this->assertSame($esperada, $modelo->fresh()->busqueda);
    }

    #[DataProvider('vectoresDeOrden')]
    public function test_deriva_la_clave_de_ordenamiento(string $lema, string $esperada): void
    {
        $modelo = Entrada::create(['mam' => $lema, 'espanol' => 'prueba']);

        $this->assertSame($esperada, $modelo->fresh()->orden_alfabetico);
    }

    /**
     * Las derivadas se calculan sobre el lema **ya normalizado**, no sobre lo que llegó. Si
     * el orden se generara antes de sustituir `õ`, `õiky` se tokenizaría como carácter
     * desconocido y ordenaría con `00` en lugar de con la posición 30.
     */
    public function test_las_derivadas_se_calculan_sobre_el_lema_normalizado(): void
    {
        $corrupta = Entrada::create(['mam' => 'õiky', 'espanol' => 'conejo'])->fresh();
        $correcta = Entrada::create(['mam' => 'ẍiky', 'espanol' => 'conejo'])->fresh();

        $this->assertSame($correcta->busqueda, $corrupta->busqueda);
        $this->assertSame($correcta->orden_alfabetico, $corrupta->orden_alfabetico);
        $this->assertSame('300610', substr($corrupta->orden_alfabetico, 0, 6));
    }

    /** Cambiar el lema tiene que arrastrar las dos derivadas; si no, quedan mintiendo. */
    public function test_recalcula_las_derivadas_al_cambiar_el_lema(): void
    {
        $entrada = Entrada::create(['mam' => 'chmol', 'espanol' => 'reunión']);

        $entrada->update(['mam' => 'tz’is']);

        $entrada->refresh();

        $this->assertSame('tz’is', $entrada->mam);
        $this->assertSame('tzis', $entrada->busqueda);
        $this->assertSame('260620', $entrada->orden_alfabetico);
    }

    /**
     * El camino de tinker y del importador: asignación directa, sin pasar por `create()` ni
     * por asignación masiva. Es la razón de que esto viva en el modelo.
     */
    public function test_la_asignacion_directa_tambien_normaliza(): void
    {
        $entrada = new Entrada;
        $entrada->mam = "q'aq'";
        $entrada->espanol = 'fuego';
        $entrada->save();

        $entrada->refresh();

        $this->assertSame('q’aq’', $entrada->mam);
        $this->assertSame('qaq', $entrada->busqueda);
    }

    /**
     * Las derivadas no son asignables en masa. Un request que las traiga no puede escribirlas,
     * ni siquiera aunque se cuele la validación: el mutator manda.
     */
    public function test_las_derivadas_no_se_aceptan_desde_fuera(): void
    {
        $entrada = Entrada::create([
            'mam' => 'chmol',
            'espanol' => 'reunión',
            'busqueda' => 'basura',
            'orden_alfabetico' => '999999',
        ]);

        $entrada->refresh();

        $this->assertSame('chmol', $entrada->busqueda);
        $this->assertSame('03131512', $entrada->orden_alfabetico);
    }

    /** Igual con `revisado`: toda entrada nace sin revisar y solo el validador la mueve. */
    public function test_una_entrada_nace_sin_revisar(): void
    {
        $entrada = Entrada::create([
            'mam' => 'chmol',
            'espanol' => 'reunión',
            'revisado' => true,
        ]);

        $this->assertFalse($entrada->fresh()->revisado);
    }

    /**
     * La prueba que justifica toda la columna derivada: ordenar en SQL da el orden del Mam.
     * `ORDER BY mam` daría otro, y en el par `xaq`/`ẍiky` ni siquiera uno estable, porque
     * `utf8mb4_unicode_ci` considera `x` y `ẍ` la misma letra.
     */
    public function test_el_listado_sale_en_orden_mam(): void
    {
        [$desordenadas, $esperadas] = self::vectorDeOrdenamiento();

        foreach ($desordenadas as $lema) {
            Entrada::create(['mam' => $lema, 'espanol' => 'prueba']);
        }

        $this->assertSame($esperadas, Entrada::query()->enOrdenMam()->pluck('mam')->all());
    }
}
