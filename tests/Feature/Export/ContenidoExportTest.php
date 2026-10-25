<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Enums\SeccionPagina;
use App\Models\Documento;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\Pagina;
use App\Models\User;
use App\Models\VersionContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * `contenido.json`: páginas por sección y documentos, para la compilación del sitio.
 *
 * Mismo contrato de seguridad que el vocabulario: token estático, solo lectura y **solo lo
 * revisado**.
 */
final class ContenidoExportTest extends TestCase
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
        return $this->withToken(self::TOKEN)
            ->getJson(route('export.contenido'))
            ->assertOk()
            ->json();
    }

    public function test_sin_token_no_devuelve_nada(): void
    {
        $this->getJson(route('export.contenido'))->assertUnauthorized();
        $this->withToken('el-que-no-es')->getJson(route('export.contenido'))->assertUnauthorized();
    }

    public function test_sin_token_configurado_el_endpoint_queda_cerrado(): void
    {
        config(['aprendemam.exportacion.token' => '']);

        $this->getJson(route('export.contenido'))->assertStatus(503);
    }

    public function test_la_sesion_del_panel_no_abre_la_exportacion(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->getJson(route('export.contenido'))
            ->assertUnauthorized();
    }

    public function test_sin_contenido_las_tres_secciones_salen_vacias_y_no_ausentes(): void
    {
        $datos = $this->exportar();

        $this->assertSame(['version', 'generado_en', 'paginas', 'documentos'], array_keys($datos));
        $this->assertSame(['cultura', 'gramatica', 'base_legal'], array_keys($datos['paginas']));
        $this->assertSame([], $datos['paginas']['cultura']);
        $this->assertSame([], $datos['paginas']['gramatica']);
        $this->assertSame([], $datos['paginas']['base_legal']);
        $this->assertSame([], $datos['documentos']);
    }

    public function test_solo_sale_lo_revisado(): void
    {
        Pagina::factory()->revisada()->create(['slug' => 'publicada']);
        Pagina::factory()->create(['slug' => 'borrador']);
        Documento::factory()->revisado()->create(['slug' => 'publicado']);
        Documento::factory()->create(['slug' => 'borrador']);

        $datos = $this->exportar();

        $this->assertSame(['publicada'], array_column($datos['paginas']['cultura'], 'slug'));
        $this->assertSame(['publicado'], array_column($datos['documentos'], 'slug'));
    }

    public function test_retirar_la_revision_saca_la_pagina_de_la_exportacion(): void
    {
        $pagina = Pagina::factory()->revisada()->create();
        $this->assertCount(1, $this->exportar()['paginas']['cultura']);

        $pagina->revisado = false;
        $pagina->save();

        $this->assertCount(0, $this->exportar()['paginas']['cultura']);
    }

    public function test_agrupa_por_seccion_y_ordena_como_decidio_el_docente(): void
    {
        Pagina::factory()->revisada()->create(['slug' => 'c2', 'orden' => 2]);
        Pagina::factory()->revisada()->create(['slug' => 'c1', 'orden' => 1]);
        Pagina::factory()->revisada()->enSeccion(SeccionPagina::Gramatica)->create(['slug' => 'g1']);
        Pagina::factory()->revisada()->enSeccion(SeccionPagina::BaseLegal)->create(['slug' => 'l1']);

        $paginas = $this->exportar()['paginas'];

        $this->assertSame(['c1', 'c2'], array_column($paginas['cultura'], 'slug'));
        $this->assertSame(['g1'], array_column($paginas['gramatica'], 'slug'));
        $this->assertSame(['l1'], array_column($paginas['base_legal'], 'slug'));
    }

    /**
     * El contrato que consume el frontend. Si cambia una clave, este test es el aviso de que
     * hay que avisarle a `frontend-aprende-mam`.
     */
    public function test_la_forma_exacta_de_una_pagina_y_de_un_documento(): void
    {
        $fuente = Fuente::factory()->create([
            'titulo' => 'Gramática Pedagógica',
            'institucion' => 'ALMG',
            'anio' => 2018,
            'url' => 'https://almg.example/gramatica',
        ]);

        Pagina::factory()->revisada()->create([
            'slug' => 'saludos',
            'titulo' => 'Los saludos',
            'cuerpo' => "## Saludos\n\nTexto.",
            'orden' => 3,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'fuente_id' => $fuente->id,
        ]);

        Documento::factory()->revisado()->create([
            'slug' => 'guia-1',
            'titulo' => 'Guía 1',
            'descripcion' => 'Para el aula.',
            'tipo' => 'cuadernillo',
            'archivo_url' => '/materiales/guia-1.pdf',
            'tamano_kb' => 640,
            'licencia' => 'CC BY',
            'orden' => 1,
        ]);

        $datos = $this->exportar();

        $this->assertSame([
            'slug' => 'saludos',
            'titulo' => 'Los saludos',
            'cuerpo' => "## Saludos\n\nTexto.",
            'orden' => 3,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'fuente' => [
                'titulo' => 'Gramática Pedagógica',
                'institucion' => 'ALMG',
                'anio' => 2018,
                'url' => 'https://almg.example/gramatica',
            ],
        ], $datos['paginas']['cultura'][0]);

        $this->assertSame([
            'slug' => 'guia-1',
            'titulo' => 'Guía 1',
            'descripcion' => 'Para el aula.',
            'tipo' => 'cuadernillo',
            'archivo_url' => '/materiales/guia-1.pdf',
            'tamano_kb' => 640,
            'licencia' => 'CC BY',
            'orden' => 1,
            'video_url' => null,
            'fuente' => null,
        ], $datos['documentos'][0]);
    }

    public function test_ningun_texto_exportado_trae_caracteres_corruptos(): void
    {
        Pagina::factory()->revisada()->create(['titulo' => "Q'anil õiky", 'cuerpo' => "q'aq' õ"]);

        $pagina = $this->exportar()['paginas']['cultura'][0];

        $this->assertDoesNotMatchRegularExpression('/[\x{00F5}\x{00D5}\x{0027}\x{02BC}]/u', $pagina['titulo'].$pagina['cuerpo']);
    }

    public function test_comparte_el_numero_de_version_con_el_vocabulario(): void
    {
        $antes = $this->exportar()['version'];
        $this->assertSame(VersionContenido::numeroActual(), $antes);

        Pagina::factory()->create();
        $despues = $this->exportar()['version'];

        $this->assertGreaterThan($antes, $despues);
        $this->assertSame($despues, $this->withToken(self::TOKEN)->getJson(route('export.version'))->json('version'));
    }

    public function test_guardar_un_documento_o_borrar_una_pagina_tambien_sube_la_version(): void
    {
        $v0 = VersionContenido::numeroActual();

        $documento = Documento::factory()->create();
        $v1 = VersionContenido::numeroActual();
        $this->assertGreaterThan($v0, $v1);

        $pagina = Pagina::factory()->create();
        $pagina->delete();
        $this->assertGreaterThan($v1, VersionContenido::numeroActual());

        $documento->update(['titulo' => 'Otro']);
        $this->assertGreaterThan($v1 + 1, VersionContenido::numeroActual());
    }

    public function test_el_vocabulario_no_cambia_de_forma(): void
    {
        Entrada::factory()->revisada()->create();
        Pagina::factory()->revisada()->create();

        $respuesta = $this->withToken(self::TOKEN)->get(route('export.vocabulario'))->assertOk();
        $json = json_decode($respuesta->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['version', 'generado_en', 'categorias', 'entradas'], array_keys($json));
    }
}
