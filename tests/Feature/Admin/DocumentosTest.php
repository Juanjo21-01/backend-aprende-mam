<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Documento;
use App\Models\Fuente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CRUD de los materiales descargables. Solo metadatos: el PDF no se sube.
 */
final class DocumentosTest extends TestCase
{
    use RefreshDatabase;

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
            'slug' => 'guia-de-lectura',
            'titulo' => 'Guía de lectura',
            'descripcion' => 'Para el aula.',
            'tipo' => 'guia',
            'archivo_url' => '/materiales/guia-de-lectura.pdf',
            'tamano_kb' => 820,
            'licencia' => 'Reproducción sin fines comerciales citando la fuente',
            ...$cambios,
        ];
    }

    public function test_sin_sesion_no_hay_acceso(): void
    {
        $this->getJson(route('admin.documentos.index'))->assertUnauthorized();
    }

    public function test_el_editor_crea_un_documento_sin_revisar(): void
    {
        $fuente = Fuente::factory()->create();
        $editor = User::factory()->editor()->create(['name' => 'Ana Pérez']);

        $this->actingAs($editor)
            ->postJson(route('admin.documentos.store'), $this->datos([
                'fuente_id' => $fuente->id,
                'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'guia')
            ->assertJsonPath('data.tipo_nombre', 'Guía')
            ->assertJsonPath('data.revisado', false)
            ->assertJsonPath('data.creado_por.nombre', 'Ana Pérez')
            ->assertJsonPath('data.fuente.id', $fuente->id)
            ->assertJsonPath('data.video_url', 'https://youtu.be/dQw4w9WgXcQ');
    }

    public function test_acepta_los_cuatro_tipos(): void
    {
        foreach (['guia', 'ficha', 'cuadernillo', 'infografia'] as $i => $tipo) {
            $this->actingAs($this->editor())
                ->postJson(route('admin.documentos.store'), $this->datos(['slug' => "doc-{$i}", 'tipo' => $tipo]))
                ->assertCreated();
        }
    }

    public function test_rechaza_un_tipo_que_no_existe(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), $this->datos(['tipo' => 'video']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    public function test_exige_slug_titulo_tipo_y_archivo(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'titulo', 'tipo', 'archivo_url']);
    }

    public function test_el_slug_es_unico(): void
    {
        Documento::factory()->create(['slug' => 'guia-de-lectura']);

        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), $this->datos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function archivosInvalidos(): array
    {
        return [
            'no es pdf' => ['/materiales/guia.docx'],
            'http' => ['http://cdn.example.org/guia.pdf'],
            'sin barra inicial' => ['materiales/guia.pdf'],
            'relativa al protocolo' => ['//cdn.example.org/guia.pdf'],
            'javascript' => ['javascript:alert(1)//.pdf'],
        ];
    }

    #[DataProvider('archivosInvalidos')]
    public function test_rechaza_una_direccion_de_pdf_que_no_sirve(string $archivo): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), $this->datos(['archivo_url' => $archivo]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo_url');
    }

    public function test_acepta_un_pdf_en_un_cdn_https(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), $this->datos([
                'archivo_url' => 'https://cdn.example.org/materiales/guia.pdf',
            ]))
            ->assertCreated();
    }

    public function test_rechaza_un_video_que_el_frontend_no_sabria_incrustar(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), $this->datos(['video_url' => 'https://youtube.com/watch']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video_url');
    }

    public function test_rechaza_un_tamano_no_positivo(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), $this->datos(['tamano_kb' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tamano_kb');
    }

    public function test_rechaza_las_columnas_que_el_formulario_no_puede_mandar(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.documentos.store'), $this->datos(['revisado' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('revisado');
    }

    public function test_el_editor_edita_y_el_administrador_borra(): void
    {
        $documento = Documento::factory()->create();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.documentos.update', $documento), ['titulo' => 'Otro'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Otro');

        $this->actingAs($this->editor())
            ->deleteJson(route('admin.documentos.destroy', $documento))
            ->assertForbidden();

        $this->actingAs($this->administrador())
            ->deleteJson(route('admin.documentos.destroy', $documento))
            ->assertNoContent();

        $this->assertModelMissing($documento);
    }

    public function test_lista_en_el_orden_del_docente_y_filtra_por_tipo(): void
    {
        Documento::factory()->create(['slug' => 'b', 'orden' => 2]);
        Documento::factory()->create(['slug' => 'a', 'orden' => 1]);
        Documento::factory()->create(['slug' => 'f', 'tipo' => 'ficha']);

        $this->actingAs($this->editor())
            ->getJson(route('admin.documentos.index', ['tipo' => 'guia']))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'a');

        $this->actingAs($this->editor())
            ->getJson(route('admin.documentos.index'))
            ->assertJsonCount(3, 'data');
    }

    public function test_solo_el_administrador_firma_la_revision(): void
    {
        $documento = Documento::factory()->create();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.documentos.revision', $documento), ['revisado' => true])
            ->assertForbidden();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.documentos.revision', $documento), ['revisado' => true])
            ->assertOk()
            ->assertJsonPath('data.revisado', true);

        $this->assertNotNull($documento->fresh()->revisado_por);
        $this->assertNotNull($documento->fresh()->revisado_en);
    }
}
