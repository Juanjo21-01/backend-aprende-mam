<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\User;
use App\Models\VersionContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * La única salida de datos del backend, y la que sostiene el modelo de despliegue entero:
 * el sitio público se compila desde aquí y después no vuelve a llamar.
 */
final class ExportacionTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    private const TOKEN = 'token-de-prueba-solo-para-esta-suite';

    protected function setUp(): void
    {
        parent::setUp();

        config(['aprendemam.exportacion.token' => self::TOKEN]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exportar(): array
    {
        $respuesta = $this->withToken(self::TOKEN)
            ->get(route('export.vocabulario'))
            ->assertOk();

        return json_decode($respuesta->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_sin_token_no_devuelve_nada(): void
    {
        $this->getJson(route('export.vocabulario'))->assertUnauthorized();
        $this->getJson(route('export.version'))->assertUnauthorized();
    }

    public function test_con_un_token_equivocado_tampoco(): void
    {
        $this->withToken('el-que-no-es')
            ->getJson(route('export.vocabulario'))
            ->assertUnauthorized();
    }

    /**
     * Una instalación recién desplegada sin `EXPORT_TOKEN` tiene que quedar cerrada, no
     * abierta: comparar dos cadenas vacías publicaría el diccionario a quien pase por la URL.
     */
    public function test_sin_token_configurado_el_endpoint_queda_cerrado(): void
    {
        config(['aprendemam.exportacion.token' => '']);

        $this->getJson(route('export.vocabulario'))->assertStatus(503);
    }

    /** La sesión del panel no sirve aquí: quien llama es un proceso, no un navegador. */
    public function test_la_sesion_del_panel_no_abre_la_exportacion(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->getJson(route('export.vocabulario'))
            ->assertUnauthorized();
    }

    /**
     * El requisito de rigor del proyecto: nada llega a manos de una estudiante sin que el
     * validador lingüístico haya dado fe de que el Mam está bien escrito.
     */
    public function test_solo_exporta_lo_revisado(): void
    {
        Entrada::factory()->conLema('chmol')->revisada()->create();
        Entrada::factory()->conLema('b’aq')->create();

        $datos = $this->exportar();

        $this->assertCount(1, $datos['entradas']);
        $this->assertSame('chmol', $datos['entradas'][0]['mam']);
    }

    public function test_sin_nada_revisado_el_diccionario_sale_vacio(): void
    {
        Entrada::factory()->count(3)->create();

        $this->assertSame([], $this->exportar()['entradas']);
    }

    public function test_las_entradas_salen_en_orden_mam(): void
    {
        foreach (['xaq', 'ẍiky', 'chmol', 'b’aq', 'ch’el'] as $lema) {
            Entrada::factory()->conLema($lema)->revisada()->create();
        }

        $this->assertSame(
            ['b’aq', 'chmol', 'ch’el', 'xaq', 'ẍiky'],
            array_column($this->exportar()['entradas'], 'mam')
        );
    }

    /**
     * El navegador no tiene por qué saber nada de la ortografía del Mam: las dos claves
     * viajan ya calculadas para que el buscador y la navegación alfabética del cliente
     * funcionen sin conexión.
     */
    public function test_cada_entrada_viaja_con_sus_claves_derivadas(): void
    {
        Entrada::factory()->conLema('tz’is')->revisada()->create();

        $entrada = $this->exportar()['entradas'][0];

        $this->assertSame('tzis', $entrada['busqueda']);
        $this->assertSame('260620', $entrada['orden']);
    }

    public function test_cada_entrada_viaja_con_su_procedencia(): void
    {
        $fuente = Fuente::factory()->create(['titulo' => 'Diccionario Mam', 'anio' => 2011]);

        Entrada::factory()->revisada()->create([
            'fuente_id' => $fuente->id,
            'pagina_fuente' => '112',
        ]);

        $entrada = $this->exportar()['entradas'][0];

        $this->assertSame('Diccionario Mam', $entrada['fuente']['titulo']);
        $this->assertSame(2011, $entrada['fuente']['anio']);
        $this->assertSame('112', $entrada['fuente']['pagina']);
    }

    public function test_las_entradas_traen_sus_temas_por_slug(): void
    {
        $animales = Categoria::factory()->create(['slug' => 'animales']);
        $entrada = Entrada::factory()->conLema('ẍiky')->revisada()->create();
        $entrada->categorias()->sync([$animales->id]);

        $datos = $this->exportar();

        $this->assertSame(['animales'], $datos['entradas'][0]['temas']);
        $this->assertSame('animales', $datos['categorias'][0]['slug']);
    }

    /**
     * La jerarquía también, y por el mismo motivo: fuera de la exportación el `id` de una
     * categoría no existe, así que un `padre` numérico no se puede resolver contra nada y
     * el sitio no puede reconstruir el árbol de temas.
     *
     * Hace falta un tema anidado para que se note. Con los siete de primer nivel que hay
     * hoy, exportar el id o el slug da igual, y por eso el fallo vivió sin que nadie lo
     * viera.
     */
    public function test_la_jerarquia_de_temas_viaja_por_slug(): void
    {
        $padre = Categoria::factory()->create(['slug' => 'la-naturaleza', 'orden' => 1]);
        Categoria::factory()->hijaDe($padre)->create(['slug' => 'plantas', 'orden' => 2]);

        $categorias = collect($this->exportar()['categorias'])->keyBy('slug');

        $this->assertNull($categorias['la-naturaleza']['padre']);
        $this->assertSame('la-naturaleza', $categorias['plantas']['padre']);
    }

    public function test_la_version_sube_al_guardar_contenido(): void
    {
        $antes = VersionContenido::numeroActual();

        Entrada::factory()->create();

        $this->assertGreaterThan($antes, VersionContenido::numeroActual());
    }

    public function test_la_version_sube_al_marcar_una_entrada_como_revisada(): void
    {
        $entrada = Entrada::factory()->create();
        $antes = VersionContenido::numeroActual();

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => true]);

        $this->assertGreaterThan($antes, VersionContenido::numeroActual());
    }

    /** La versión no puede retroceder al borrar, o los clientes se quedan con lo viejo. */
    public function test_la_version_no_retrocede_al_borrar(): void
    {
        $entrada = Entrada::factory()->create();
        $antes = VersionContenido::numeroActual();

        $entrada->delete();

        $this->assertGreaterThan($antes, VersionContenido::numeroActual());
    }

    /** El endpoint ligero: el build pregunta esto antes de bajarse el vocabulario entero. */
    public function test_el_endpoint_de_version_coincide_con_el_del_vocabulario(): void
    {
        Entrada::factory()->revisada()->create();

        $version = $this->withToken(self::TOKEN)
            ->getJson(route('export.version'))
            ->assertOk()
            ->json('version');

        $this->assertSame(VersionContenido::numeroActual(), $version);
        $this->assertSame($version, $this->exportar()['version']);
    }
}
