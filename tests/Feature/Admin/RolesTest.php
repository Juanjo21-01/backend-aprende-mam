<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * La regla de los dos roles: el editor carga y corrige, el administrador además borra y
 * marca lo revisado.
 *
 * Es lo que hace viable el flujo del Manual de Normas —cargar rápido y revisar después—
 * sin que una sesión de carga pueda perder trabajo ajeno por un clic, y lo que garantiza
 * que nada se publique sin pasar por el validador lingüístico.
 */
final class RolesTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    public function test_el_editor_crea_y_edita_entradas(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'chmol',
                'espanol' => 'reunión',
            ])
            ->assertCreated();

        $entrada = Entrada::query()->firstOrFail();

        $this->actingAs($editor)
            ->patchJson(route('admin.entradas.update', $entrada), ['espanol' => 'junta'])
            ->assertOk();
    }

    public function test_el_editor_no_borra_entradas(): void
    {
        $entrada = Entrada::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->deleteJson(route('admin.entradas.destroy', $entrada))
            ->assertForbidden();

        $this->assertDatabaseHas('entradas', ['id' => $entrada->id]);
    }

    public function test_el_editor_no_borra_categorias_ni_fuentes(): void
    {
        $editor = User::factory()->editor()->create();
        $categoria = Categoria::factory()->create();
        $fuente = Fuente::factory()->create();

        $this->actingAs($editor)
            ->deleteJson(route('admin.categorias.destroy', $categoria))
            ->assertForbidden();

        $this->actingAs($editor)
            ->deleteJson(route('admin.fuentes.destroy', $fuente))
            ->assertForbidden();

        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
        $this->assertDatabaseHas('fuentes', ['id' => $fuente->id]);
    }

    /**
     * La atribución del validador lingüístico. Es también el interruptor de publicación:
     * quien pueda moverla decide qué llega al sitio público.
     */
    public function test_el_editor_no_marca_una_entrada_como_revisada(): void
    {
        $entrada = Entrada::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => true])
            ->assertForbidden();

        $this->assertFalse($entrada->fresh()->revisado);
    }

    public function test_el_administrador_borra_una_entrada(): void
    {
        $entrada = Entrada::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->deleteJson(route('admin.entradas.destroy', $entrada))
            ->assertNoContent();

        $this->assertDatabaseMissing('entradas', ['id' => $entrada->id]);
    }

    public function test_el_administrador_marca_una_entrada_como_revisada(): void
    {
        $entrada = Entrada::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => true])
            ->assertOk()
            ->assertJsonPath('data.revisado', true);

        $this->assertTrue($entrada->fresh()->revisado);
    }

    /** Se puede volver atrás: aprobar algo por error tiene que ser reversible. */
    public function test_el_administrador_puede_retirar_la_revision(): void
    {
        $entrada = Entrada::factory()->revisada()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.entradas.revision', $entrada), ['revisado' => false])
            ->assertOk();

        $this->assertFalse($entrada->fresh()->revisado);
    }

    public function test_el_403_explica_el_motivo(): void
    {
        $entrada = Entrada::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->deleteJson(route('admin.entradas.destroy', $entrada))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede borrar contenido.');
    }
}
