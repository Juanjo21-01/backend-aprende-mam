<?php

declare(strict_types=1);

namespace Tests\Feature\Importacion;

use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\VersionContenido;
use Database\Seeders\CategoriasVocabularioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * `php artisan mam:importar-vocabulario`.
 *
 * Lee el CSV intermedio que sale de un PDF fuente —una fila por variante, fiel a la fuente— y
 * lo convierte en entradas sin revisar. Lo que se prueba aquí es lo que no se ve en pantalla
 * si sale mal: la ortografía de lo que entra, que nada se duplique y que una tanda con un
 * error no deje media importación hecha.
 */
final class ImportarVocabularioTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalogs;

    private const ENCABEZADO = ['mam', 'espanol', 'pagina', 'tema', 'tema_origen', 'estado', 'notas'];

    private Fuente $fuente;

    /** @var list<string> */
    private array $temporales = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategoriasVocabularioSeeder::class);

        $this->fuente = Fuente::query()->create([
            'titulo' => 'Neologismos y vocabulario pedagógico Mam',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }

        parent::tearDown();
    }

    public function test_importa_las_filas_listas_con_la_ortografia_normalizada(): void
    {
        $archivo = $this->csv([
            // Mayúscula inicial, apóstrofo recto y `õ` por `ẍ`: todo como llega del PDF.
            ['Õi\'wil', 'Zorrillo', '199', 'animales', 'propuesto', 'ok', ''],
            ['Tutz\'b\'il', 'Banco, silla', '22', 'la-casa', 'propuesto', 'ok', ''],
        ]);

        $this->importar($archivo)->assertSuccessful();

        $zorrillo = Entrada::query()->where('espanol', 'Zorrillo')->sole();

        $this->assertSame('ẍi’wil', $zorrillo->mam);
        $this->assertSame('xiwil', $zorrillo->busqueda);
        $this->assertSame($this->fuente->id, $zorrillo->fuente_id);
        $this->assertSame('199', $zorrillo->pagina_fuente);
        $this->assertNull($zorrillo->categoria_gramatical_id);
        $this->assertSame(['animales'], $zorrillo->categorias->pluck('slug')->all());

        $this->assertSame('tutz’b’il', Entrada::query()->where('espanol', 'Banco, silla')->sole()->mam);
    }

    /** La regla de rigor: nada importado llega al sitio sin pasar por el validador. */
    public function test_todo_lo_importado_nace_sin_revisar(): void
    {
        $this->importar($this->csv([
            ['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', ''],
        ]))->assertSuccessful();

        $entrada = Entrada::query()->sole();

        $this->assertFalse($entrada->revisado);
        $this->assertNull($entrada->revisado_por);
    }

    /**
     * La misma forma en varias filas es una sola palabra con varias acepciones: se junta el
     * castellano, las páginas y los temas. La comparación no distingue mayúsculas, porque la
     * fuente capitaliza la primera variante de cada fila y no las demás.
     */
    public function test_fusiona_la_misma_forma_en_una_sola_entrada(): void
    {
        $this->importar($this->csv([
            ['Q\'a\'jtz', 'Banco, silla', '22', 'la-casa', 'propuesto', 'ok', ''],
            ['q\'a\'jtz', 'Silla, colocar', '172', 'la-casa|acciones', 'propuesto', 'ok', ''],
            ['q\'a\'jtz', 'Banco, silla', '22', 'la-casa', 'propuesto', 'ok', 'repetida'],
        ]))->assertSuccessful();

        $entrada = Entrada::query()->sole();

        $this->assertSame('q’a’jtz', $entrada->mam);
        $this->assertSame('Banco, silla; Silla, colocar', $entrada->espanol);
        $this->assertSame('22, 172', $entrada->pagina_fuente);
        $this->assertEqualsCanonicalizing(['la-casa', 'acciones'], $entrada->categorias->pluck('slug')->all());
    }

    /** Las páginas se listan en orden numérico, no de aparición ni alfabético. */
    public function test_ordena_las_paginas_numericamente(): void
    {
        $this->importar($this->csv([
            ['tnam', 'Pueblo', '112', 'la-comunidad', 'propuesto', 'ok', ''],
            ['tnam', 'Municipio', '9', 'la-comunidad', 'propuesto', 'ok', ''],
        ]))->assertSuccessful();

        $this->assertSame('9, 112', Entrada::query()->sole()->pagina_fuente);
    }

    /**
     * Frases, filas marcadas para corregir y lo que no encajó en ningún tema se quedan en el
     * CSV. No son errores: son decisiones ya tomadas sobre esas filas.
     */
    public function test_deja_fuera_frases_filas_por_revisar_y_sin_tema(): void
    {
        $this->importar($this->csv([
            ['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', ''],
            ['Jqonktza ja', 'Abra la puerta', '2', '', '', 'frase', ''],
            ['pCahderlinl', 'Abrazar', '2', 'acciones', 'propuesto', 'revisar', 'columnas mezcladas'],
            ['ajtzqiltxkup', 'Zoólogo', '199', '', '', 'ok', ''],
        ]))
            ->expectsOutputToContain('Frases: 1')
            ->expectsOutputToContain('Por revisar: 1')
            ->expectsOutputToContain('Sin tema: 1')
            ->expectsOutputToContain('Entradas nuevas: 1')
            ->assertSuccessful();

        $this->assertSame(['txkup'], Entrada::query()->pluck('mam')->all());
    }

    /**
     * Una forma que ya está en la base no se toca: puede estar firmada por el validador, y
     * el panel es la fuente de verdad una vez que algo se importó. Se reporta y nada más.
     */
    public function test_no_modifica_las_entradas_que_ya_existen(): void
    {
        $existente = Entrada::query()->create([
            'mam' => 'tutz’b’il',
            'espanol' => 'silla',
            'fuente_id' => $this->fuente->id,
        ]);
        $existente->revisado = true;
        $existente->save();

        $this->importar($this->csv([
            ['Tutz\'b\'il', 'Banco, silla', '22', 'la-casa', 'propuesto', 'ok', ''],
        ]))
            ->expectsOutputToContain('Ya existentes: 1')
            ->assertSuccessful();

        $existente->refresh();

        $this->assertSame(1, Entrada::query()->count());
        $this->assertSame('silla', $existente->espanol);
        $this->assertNull($existente->pagina_fuente);
        $this->assertTrue($existente->revisado);
        $this->assertSame(0, $existente->categorias()->count());
    }

    /**
     * Comparar en PHP y no con `WHERE mam = ?`: para `utf8mb4_unicode_ci`, `xaq` y `ẍaq` son la
     * misma cadena. Si el importador se fiara del cotejamiento, daría por existente una palabra
     * distinta y no la importaría.
     */
    public function test_no_confunde_x_con_x_dieresis_al_buscar_existentes(): void
    {
        Entrada::query()->create(['mam' => 'xaq', 'espanol' => 'piedra']);

        $this->importar($this->csv([
            ['ẍaq', 'Prueba', '1', 'la-naturaleza', 'propuesto', 'ok', ''],
        ]))->assertSuccessful();

        $this->assertEqualsCanonicalizing(['xaq', 'ẍaq'], Entrada::query()->pluck('mam')->all());
    }

    public function test_volver_a_correrlo_no_duplica_nada(): void
    {
        $archivo = $this->csv([
            ['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', ''],
        ]);

        $this->importar($archivo)->assertSuccessful();
        $this->importar($archivo)
            ->expectsOutputToContain('Entradas nuevas: 0')
            ->assertSuccessful();

        $this->assertSame(1, Entrada::query()->count());
    }

    /**
     * Todo o nada. Una sola fila con una letra que no existe en el Mam detiene la tanda entera
     * y dice dónde está, para corregir el CSV y volver a correr.
     */
    public function test_una_letra_fuera_del_alfabeto_detiene_toda_la_importacion(): void
    {
        $this->importar($this->csv([
            ['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', ''],
            ['tutz\'bil', 'Silla', '22', 'la-casa', 'propuesto', 'ok', ''],
        ]))
            ->expectsOutputToContain('Línea 3: «tutz’bil» tiene caracteres fuera del alfabeto Mam: «b».')
            ->assertFailed();

        $this->assertSame(0, Entrada::query()->count());
    }

    /** Las filas que se dejan fuera no se validan: justamente están ahí para corregirse. */
    public function test_no_valida_la_ortografia_de_lo_que_no_se_importa(): void
    {
        $this->importar($this->csv([
            ['pCahderlinl', 'Abrazar', '2', '', '', 'revisar', ''],
        ]))->assertSuccessful();
    }

    public function test_un_tema_que_no_existe_detiene_la_importacion(): void
    {
        $this->importar($this->csv([
            ['txkup', 'Animal', '11', 'animales|bichos', 'propuesto', 'ok', ''],
        ]))
            ->expectsOutputToContain('Línea 2: el tema «bichos» no existe')
            ->assertFailed();

        $this->assertSame(0, Entrada::query()->count());
        $this->assertSame(16, Categoria::query()->count());
    }

    /**
     * Un `expectsOutputToContain` por línea de salida: Laravel consume cada línea con la primera
     * expectativa que la satisface, así que dos sobre la misma línea no se cumplen nunca.
     *
     * Cada error con su línea y todos de una vez: corregir de uno en uno, volviendo a correr
     * entre medias, sería eterno con cinco mil filas.
     *
     * @return array<string, array{list<string>, string}>
     */
    public static function filasInvalidas(): array
    {
        return [
            'sin mam' => [['', 'Animal', '11', 'animales', 'propuesto', 'ok', ''], 'falta la forma en Mam'],
            'sin castellano' => [['txkup', '', '11', 'animales', 'propuesto', 'ok', ''], 'falta el castellano'],
            'página no numérica' => [['txkup', 'Animal', 'xi', 'animales', 'propuesto', 'ok', ''], 'la página «xi»'],
            'sin página' => [['txkup', 'Animal', '', 'animales', 'propuesto', 'ok', ''], 'la página «»'],
            'estado desconocido' => [['txkup', 'Animal', '11', 'animales', 'propuesto', 'listo', ''], 'el estado «listo»'],
        ];
    }

    /** @param list<string> $fila */
    #[DataProvider('filasInvalidas')]
    public function test_rechaza_filas_incompletas(array $fila, string $mencion): void
    {
        $this->importar($this->csv([$fila]))
            ->expectsOutputToContain('Línea 2: '.$mencion)
            ->assertFailed();

        $this->assertSame(0, Entrada::query()->count());
    }

    /**
     * `pagina_fuente` mide 20 caracteres. Una palabra repetida en muchas páginas no cabe, y
     * recortarla en silencio rompería justo la trazabilidad que la columna existe para dar.
     */
    public function test_rechaza_una_lista_de_paginas_que_no_cabe(): void
    {
        $filas = array_map(
            fn (int $pagina) => ['tnam', 'Pueblo', (string) $pagina, 'la-comunidad', 'propuesto', 'ok', ''],
            [101, 102, 103, 104, 105, 106]
        );

        $this->importar($this->csv($filas))
            ->expectsOutputToContain('«tnam» aparece en demasiadas páginas')
            ->assertFailed();

        $this->assertSame(0, Entrada::query()->count());
    }

    public function test_rechaza_un_csv_sin_las_columnas_requeridas(): void
    {
        $ruta = $this->archivo("mam,espanol\ntxkup,Animal\n");

        $this->importar($ruta)
            ->expectsOutputToContain('pagina')
            ->assertFailed();
    }

    public function test_rechaza_un_archivo_que_no_existe(): void
    {
        $this->importar('/no/existe.csv')->assertFailed();
    }

    public function test_rechaza_una_fuente_que_no_existe(): void
    {
        $this->artisan('mam:importar-vocabulario', [
            'archivo' => $this->csv([['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', '']]),
            '--fuente' => 999999,
        ])->assertFailed();

        $this->assertSame(0, Entrada::query()->count());
    }

    /** Lo que guarda Excel: con BOM al principio. Sin quitarlo, la primera columna no se llama `mam`. */
    public function test_acepta_un_csv_con_bom(): void
    {
        $ruta = $this->archivo(
            "\u{FEFF}".implode(',', self::ENCABEZADO)."\n"."txkup,Animal,11,animales,propuesto,ok,\n"
        );

        $this->importar($ruta)->assertSuccessful();

        $this->assertSame(1, Entrada::query()->count());
    }

    public function test_simular_reporta_sin_guardar_nada(): void
    {
        $this->artisan('mam:importar-vocabulario', [
            'archivo' => $this->csv([['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', '']]),
            '--fuente' => $this->fuente->id,
            '--simular' => true,
        ])
            ->expectsOutputToContain('Entradas nuevas: 1')
            ->expectsOutputToContain('Simulación')
            ->assertSuccessful();

        $this->assertSame(0, Entrada::query()->count());
    }

    /**
     * Lo importado nace sin revisar y por tanto no cambia la exportación: subir la versión o
     * encolar una publicación por cada alta sería trabajo para nada. Cuando el validador firme,
     * su revisión es la que publica.
     */
    public function test_no_sube_la_version_ni_programa_publicacion(): void
    {
        Queue::fake();
        $version = VersionContenido::numeroActual();

        $this->importar($this->csv([
            ['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', ''],
            ['tnam', 'Pueblo', '112', 'la-comunidad', 'propuesto', 'ok', ''],
        ]))->assertSuccessful();

        $this->assertSame(2, Entrada::query()->count());
        $this->assertSame($version, VersionContenido::numeroActual());
        Queue::assertNothingPushed();
    }

    /** Silenciar la publicación es solo durante la importación: después, todo vuelve a publicar. */
    public function test_la_publicacion_vuelve_a_funcionar_despues(): void
    {
        $this->importar($this->csv([
            ['txkup', 'Animal', '11', 'animales', 'propuesto', 'ok', ''],
        ]))->assertSuccessful();

        $version = VersionContenido::numeroActual();

        Entrada::query()->create(['mam' => 'tnam', 'espanol' => 'Pueblo']);

        $this->assertSame($version + 1, VersionContenido::numeroActual());
    }

    private function importar(string $archivo): PendingCommand
    {
        return $this->artisan('mam:importar-vocabulario', [
            'archivo' => $archivo,
            '--fuente' => $this->fuente->id,
        ]);
    }

    /** @param list<list<string>> $filas */
    private function csv(array $filas): string
    {
        $flujo = fopen('php://temp', 'r+');
        fputcsv($flujo, self::ENCABEZADO, escape: '');

        foreach ($filas as $fila) {
            fputcsv($flujo, $fila, escape: '');
        }

        rewind($flujo);
        $contenido = (string) stream_get_contents($flujo);
        fclose($flujo);

        return $this->archivo($contenido);
    }

    private function archivo(string $contenido): string
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'vocab');
        file_put_contents($ruta, $contenido);
        $this->temporales[] = $ruta;

        return $ruta;
    }
}
