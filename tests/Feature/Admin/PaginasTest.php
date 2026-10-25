<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SeccionPagina;
use App\Models\Fuente;
use App\Models\Pagina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CRUD de las páginas editoriales: cultura, gramática y base legal.
 */
final class PaginasTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    private function editor(): User
    {
        return User::factory()->editor()->create();
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->create();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'seccion' => 'cultura',
            'slug' => 'los-cuatro-pueblos',
            'titulo' => 'Los cuatro pueblos',
            'cuerpo' => "## Historia\n\nTexto con [enlace](https://example.org).",
            ...$cambios,
        ];
    }

    public function test_sin_sesion_no_hay_acceso(): void
    {
        $this->getJson(route('admin.paginas.index'))->assertUnauthorized();
    }

    public function test_el_editor_crea_una_pagina_sin_revisar_y_con_su_autoria(): void
    {
        $editor = User::factory()->editor()->create(['name' => 'Ana Pérez']);

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.store'), $this->datos(['video_url' => self::VIDEO]))
            ->assertCreated()
            ->assertJsonPath('data.seccion', 'cultura')
            ->assertJsonPath('data.seccion_nombre', 'Cultura')
            ->assertJsonPath('data.revisado', false)
            ->assertJsonPath('data.revisado_por', null)
            ->assertJsonPath('data.creado_por.nombre', 'Ana Pérez')
            ->assertJsonPath('data.video_url', self::VIDEO);

        $this->assertSame($editor->id, Pagina::query()->firstOrFail()->creado_por);
    }

    public function test_normaliza_al_guardar_pero_no_rechaza_el_apostrofo_recto(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos([
                'titulo' => "El q'aq' y la õ",
                'cuerpo' => "Palabra: q'aq'.",
            ]))
            ->assertCreated()
            ->assertJsonPath('data.titulo', 'El q’aq’ y la ẍ')
            ->assertJsonPath('data.cuerpo', 'Palabra: q’aq’.');
    }

    public function test_exige_seccion_slug_titulo_y_cuerpo(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seccion', 'slug', 'titulo', 'cuerpo']);
    }

    public function test_rechaza_una_seccion_que_no_existe(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['seccion' => 'juegos']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('seccion');
    }

    public function test_rechaza_un_slug_con_formato_invalido(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['slug' => 'Los Cuatro Pueblos']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_el_slug_es_unico_dentro_de_la_seccion_pero_no_entre_secciones(): void
    {
        Pagina::factory()->create(['seccion' => SeccionPagina::Cultura, 'slug' => 'historia']);

        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['slug' => 'historia']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['slug' => 'historia', 'seccion' => 'base_legal']))
            ->assertCreated();
    }

    public function test_mover_una_pagina_de_seccion_comprueba_el_slug_contra_la_nueva(): void
    {
        Pagina::factory()->create(['seccion' => SeccionPagina::BaseLegal, 'slug' => 'historia']);
        $pagina = Pagina::factory()->create(['seccion' => SeccionPagina::Cultura, 'slug' => 'historia']);

        $this->actingAs($this->editor())
            ->patchJson(route('admin.paginas.update', $pagina), ['seccion' => 'base_legal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_editar_sin_cambiar_el_slug_no_choca_consigo_misma(): void
    {
        $pagina = Pagina::factory()->create(['slug' => 'historia']);

        $this->actingAs($this->editor())
            ->patchJson(route('admin.paginas.update', $pagina), ['slug' => 'historia', 'titulo' => 'Nuevo título'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Nuevo título');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cuerposInvalidos(): array
    {
        return [
            'html' => ['<b>hola</b>'],
            'enlace peligroso' => ['[clic](javascript:alert(1))'],
            'imagen por http' => ['![x](http://example.org/a.png)'],
        ];
    }

    #[DataProvider('cuerposInvalidos')]
    public function test_rechaza_un_cuerpo_que_no_es_markdown_simple(string $cuerpo): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['cuerpo' => $cuerpo]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cuerpo');
    }

    public function test_acepta_imagenes_por_https_y_por_ruta_del_sitio(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos([
                'cuerpo' => "![a](/img/a.png)\n\n![b](https://cdn.example.org/b.jpg)",
            ]))
            ->assertCreated();
    }

    public function test_rechaza_un_video_de_un_proveedor_no_permitido(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['video_url' => 'https://vimeo.com/123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video_url');
    }

    public function test_acepta_el_codigo_iframe_y_guarda_solo_la_url(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos([
                'video_url' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.video_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    public function test_rechaza_las_columnas_que_el_formulario_no_puede_mandar(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['revisado' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('revisado');
    }

    public function test_asocia_una_fuente(): void
    {
        $fuente = Fuente::factory()->create();

        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['fuente_id' => $fuente->id]))
            ->assertCreated()
            ->assertJsonPath('data.fuente.id', $fuente->id);
    }

    public function test_rechaza_una_fuente_inexistente(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.paginas.store'), $this->datos(['fuente_id' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fuente_id');
    }

    public function test_lista_en_orden_y_filtra_por_seccion(): void
    {
        Pagina::factory()->create(['seccion' => SeccionPagina::Cultura, 'slug' => 'b', 'orden' => 2]);
        Pagina::factory()->create(['seccion' => SeccionPagina::Cultura, 'slug' => 'a', 'orden' => 1]);
        Pagina::factory()->create(['seccion' => SeccionPagina::Gramatica, 'slug' => 'g']);

        $this->actingAs($this->editor())
            ->getJson(route('admin.paginas.index', ['seccion' => 'cultura']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'a')
            ->assertJsonPath('data.1.slug', 'b');

        $this->actingAs($this->editor())
            ->getJson(route('admin.paginas.index'))
            ->assertJsonCount(3, 'data');
    }

    public function test_el_editor_puede_editar_una_pagina(): void
    {
        $pagina = Pagina::factory()->create();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.paginas.update', $pagina), ['orden' => 4])
            ->assertOk()
            ->assertJsonPath('data.orden', 4);
    }

    public function test_el_editor_no_puede_borrar(): void
    {
        $pagina = Pagina::factory()->create();

        $this->actingAs($this->editor())
            ->deleteJson(route('admin.paginas.destroy', $pagina))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede borrar contenido.');

        $this->assertModelExists($pagina);
    }

    public function test_el_administrador_borra(): void
    {
        $pagina = Pagina::factory()->create();

        $this->actingAs($this->administrador())
            ->deleteJson(route('admin.paginas.destroy', $pagina))
            ->assertNoContent();

        $this->assertModelMissing($pagina);
    }

    public function test_borrar_una_fuente_no_borra_las_paginas_que_la_citaban(): void
    {
        $fuente = Fuente::factory()->create();
        $pagina = Pagina::factory()->create(['fuente_id' => $fuente->id]);

        $fuente->delete();

        $this->assertNull($pagina->fresh()->fuente_id);
    }

    // --- Revisión -------------------------------------------------------------------

    public function test_el_editor_no_puede_marcar_como_revisada(): void
    {
        $pagina = Pagina::factory()->create();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.paginas.revision', $pagina), ['revisado' => true])
            ->assertForbidden();

        $this->assertFalse($pagina->fresh()->revisado);
    }

    public function test_el_administrador_firma_la_revision_y_queda_su_rastro(): void
    {
        $pagina = Pagina::factory()->create();
        $admin = User::factory()->administrador()->create(['name' => 'Luis Validador']);

        $this->actingAs($admin)
            ->patchJson(route('admin.paginas.revision', $pagina), ['revisado' => true])
            ->assertOk()
            ->assertJsonPath('data.revisado', true)
            ->assertJsonPath('data.revisado_por.nombre', 'Luis Validador');

        $this->assertNotNull($pagina->fresh()->revisado_en);
    }

    public function test_retirar_la_revision_borra_el_rastro(): void
    {
        $pagina = Pagina::factory()->revisada()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.paginas.revision', $pagina), ['revisado' => false])
            ->assertOk()
            ->assertJsonPath('data.revisado', false)
            ->assertJsonPath('data.revisado_por', null);

        $this->assertNull($pagina->fresh()->revisado_en);
    }

    public function test_corregir_una_pagina_revisada_no_mueve_la_fecha_de_revision(): void
    {
        $this->travelTo(now()->subDay());
        $pagina = Pagina::factory()->revisada()->create();
        $firmada = $pagina->fresh()->revisado_en;
        $this->travelBack();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.paginas.update', $pagina), ['titulo' => 'Corregido'])
            ->assertOk();

        $this->assertEquals($firmada, $pagina->fresh()->revisado_en);
    }

    public function test_la_revision_exige_un_booleano(): void
    {
        $pagina = Pagina::factory()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.paginas.revision', $pagina), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('revisado');
    }
}
