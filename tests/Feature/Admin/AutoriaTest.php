<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * Quién cargó cada palabra y quién dio fe de que está bien escrita.
 *
 * El sistema tiene dos roles justamente para separar quién carga de quién aprueba, así que
 * guardar **que** una entrada está revisada sin guardar por quién dejaba la trazabilidad a
 * medias. Y es un dato que no se reconstruye hacia atrás.
 */
final class AutoriaTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    public function test_anota_quien_cargo_la_entrada(): void
    {
        $editor = User::factory()->editor()->create(['name' => 'Ana Pérez']);

        $this->actingAs($editor)
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'chmol',
                'espanol' => 'reunión',
            ])
            ->assertCreated()
            ->assertJsonPath('data.creado_por.nombre', 'Ana Pérez');

        $this->assertSame($editor->id, Entrada::query()->firstOrFail()->creado_por);
    }

    public function test_una_entrada_nueva_no_tiene_revisor(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->postJson(route('admin.entradas.store'), ['mam' => 'jal', 'espanol' => 'mazorca'])
            ->assertCreated()
            ->assertJsonPath('data.revisado_por', null)
            ->assertJsonPath('data.revisado_en', null);
    }

    public function test_anota_quien_reviso_y_cuando(): void
    {
        $entrada = Entrada::factory()->create();
        $validador = User::factory()->administrador()->create(['name' => 'María López']);

        $this->actingAs($validador)
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => true])
            ->assertOk()
            ->assertJsonPath('data.revisado_por.nombre', 'María López');

        $entrada->refresh();

        $this->assertSame($validador->id, $entrada->revisado_por);
        $this->assertNotNull($entrada->revisado_en);
    }

    /**
     * Dejar el nombre del validador en una entrada que ya no está aprobada haría creer que
     * la aprobó. Al retirar la revisión, el rastro se borra con ella.
     */
    public function test_retirar_la_revision_borra_el_rastro(): void
    {
        $entrada = Entrada::factory()->create();
        $validador = User::factory()->administrador()->create();

        $this->actingAs($validador)
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => true])
            ->assertOk();

        $this->actingAs($validador)
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => false])
            ->assertOk();

        $entrada->refresh();

        $this->assertNull($entrada->revisado_por);
        $this->assertNull($entrada->revisado_en);
    }

    /**
     * Corregir una tilde de la traducción no puede reescribir la fecha en que se validó el
     * Mam: son dos hechos distintos y `updated_at` ya registra el segundo.
     */
    public function test_editar_una_entrada_revisada_no_toca_la_fecha_de_revision(): void
    {
        $entrada = Entrada::factory()->create();
        $validador = User::factory()->administrador()->create();

        $this->actingAs($validador)
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => true]);

        $revisadoEn = $entrada->fresh()->revisado_en;

        $this->travel(5)->minutes();

        $this->actingAs(User::factory()->editor()->create())
            ->patchJson(route('admin.entradas.update', $entrada), ['espanol' => 'otra acepción'])
            ->assertOk();

        $entrada->refresh();

        $this->assertEquals($revisadoEn, $entrada->revisado_en);
        $this->assertSame($validador->id, $entrada->revisado_por);
    }

    /**
     * Una importación masiva no la cargó una persona. Decir que sí sería peor que no
     * saberlo, así que la autoría queda nula.
     */
    public function test_sin_sesion_la_autoria_queda_nula(): void
    {
        $entrada = Entrada::factory()->create();

        $this->assertNull($entrada->creado_por);
    }

    /** Igual que las columnas derivadas: la autoría la pone el sistema, no el formulario. */
    public function test_la_autoria_no_se_acepta_desde_el_request(): void
    {
        $editor = User::factory()->editor()->create();
        $otro = User::factory()->administrador()->create();

        $this->actingAs($editor)
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'chmol',
                'espanol' => 'reunión',
                'creado_por' => $otro->id,
                'revisado_por' => $otro->id,
            ])
            ->assertCreated();

        $entrada = Entrada::query()->firstOrFail();

        $this->assertSame($editor->id, $entrada->creado_por);
        $this->assertNull($entrada->revisado_por);
    }

    /**
     * El sitio público no necesita saber quién cargó una palabra, y publicar los nombres
     * del personal docente en un JSON servido desde un CDN no tendría ningún sentido.
     */
    public function test_la_exportacion_no_lleva_autoria(): void
    {
        config(['aprendemam.exportacion.token' => 'token-de-prueba']);

        $entrada = Entrada::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => true]);

        $respuesta = $this->withToken('token-de-prueba')
            ->get(route('export.vocabulario'))
            ->assertOk();

        $exportada = json_decode($respuesta->streamedContent(), true, flags: JSON_THROW_ON_ERROR)['entradas'][0];

        foreach (['creado_por', 'revisado_por', 'revisado_en'] as $campo) {
            $this->assertArrayNotHasKey($campo, $exportada);
        }
    }
}
