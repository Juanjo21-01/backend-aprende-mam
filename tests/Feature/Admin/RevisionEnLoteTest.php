<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Entrada;
use App\Models\User;
use App\Models\VersionContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * Firmar la revisión de una tanda entera.
 *
 * Existe por una cuenta concreta: el corpus del COLIMAM son 6,185 entradas y aprobarlas de
 * a un clic es media jornada de clics. El flujo previsto es filtrar por fuente o por tema,
 * mirar lo que sale, y firmar la página.
 *
 * Sigue siendo la atribución del validador lingüístico, con la misma política que la
 * revisión individual: en lote no se relaja nada.
 */
final class RevisionEnLoteTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    private function administrador(): User
    {
        return User::factory()->administrador()->create();
    }

    public function test_marca_varias_entradas_de_una_vez(): void
    {
        $entradas = Entrada::factory()->count(3)->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => $entradas->pluck('id')->all(),
                'revisado' => true,
            ])
            ->assertOk()
            ->assertJson(['recibidas' => 3, 'actualizadas' => 3]);

        $this->assertSame(3, Entrada::where('revisado', true)->count());
    }

    /** Se permite volver atrás en lote, igual que de a una. */
    public function test_tambien_quita_la_revision_en_lote(): void
    {
        $entradas = Entrada::factory()->count(2)->revisada()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => $entradas->pluck('id')->all(),
                'revisado' => false,
            ])
            ->assertOk()
            ->assertJson(['actualizadas' => 2]);

        $this->assertSame(0, Entrada::where('revisado', true)->count());
    }

    /**
     * Un editor carga y corrige, pero no da fe de que el Mam esté bien escrito. Que la
     * acción sea en lote no cambia de quién es la atribución.
     */
    public function test_un_editor_no_puede_firmar_revisiones_en_lote(): void
    {
        $entradas = Entrada::factory()->count(2)->create();

        $this->actingAs(User::factory()->editor()->create())
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => $entradas->pluck('id')->all(),
                'revisado' => true,
            ])
            ->assertForbidden();

        $this->assertSame(0, Entrada::where('revisado', true)->count());
    }

    /**
     * Las que ya estaban como se piden no se tocan, y eso se nota donde importa: el número
     * de versión del contenido solo sube por las que de verdad cambiaron. Si no, firmar una
     * página donde casi todo ya estaba revisado dispararía una publicación por cada fila.
     */
    public function test_las_que_ya_estaban_asi_no_cuentan_ni_suben_la_version(): void
    {
        $yaRevisadas = Entrada::factory()->count(2)->revisada()->create();
        $pendiente = Entrada::factory()->create();

        $versionAntes = VersionContenido::numeroActual();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => [...$yaRevisadas->pluck('id')->all(), $pendiente->id],
                'revisado' => true,
            ])
            ->assertOk()
            ->assertJson(['recibidas' => 3, 'actualizadas' => 1]);

        $this->assertSame($versionAntes + 1, VersionContenido::numeroActual());
    }

    /** La autoría de la revisión se anota también en lote: es la firma de alguien. */
    public function test_deja_el_rastro_de_quien_firmo(): void
    {
        $validador = $this->administrador();
        $entrada = Entrada::factory()->create();

        $this->actingAs($validador)
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => [$entrada->id],
                'revisado' => true,
            ])
            ->assertOk();

        $entrada->refresh();

        $this->assertSame($validador->id, $entrada->revisado_por);
        $this->assertNotNull($entrada->revisado_en);
    }

    public function test_exige_una_lista_y_la_marca(): void
    {
        $this->actingAs($this->administrador())
            ->patchJson(route('admin.entradas.revisiones'), ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ids' => 'No se indicó ninguna entrada.',
                'revisado' => 'Falta indicar si las entradas quedan revisadas o no.',
            ]);
    }

    /** El tope es el mismo que el máximo de `por_pagina`: se firma lo que se está viendo. */
    public function test_rechaza_una_tanda_demasiado_grande(): void
    {
        $this->actingAs($this->administrador())
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => range(1, 201),
                'revisado' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ids' => 'Son demasiadas entradas de una vez: el máximo es 200.',
            ]);
    }

    public function test_rechaza_una_entrada_que_no_existe(): void
    {
        $entrada = Entrada::factory()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => [$entrada->id, 999_999],
                'revisado' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.1' => 'Alguna de las entradas ya no existe.']);

        $this->assertFalse($entrada->fresh()->revisado);
    }

    /**
     * La ruta del lote va declarada antes que `entradas/{entrada}`, que casa con la misma
     * forma. Si alguien la mueve de sitio, esto responde 404 buscando una entrada llamada
     * «revisiones» — y el test lo dice antes que el usuario.
     */
    public function test_la_ruta_del_lote_no_la_atrapa_la_de_una_entrada(): void
    {
        $this->actingAs($this->administrador())
            ->patchJson(route('admin.entradas.revisiones'), [
                'ids' => [Entrada::factory()->create()->id],
                'revisado' => true,
            ])
            ->assertOk();
    }
}
