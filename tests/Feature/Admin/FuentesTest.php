<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * CRUD de fuentes bibliográficas: la trazabilidad que exige el proyecto de graduación.
 */
final class FuentesTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    private function editor(): User
    {
        return User::factory()->editor()->create();
    }

    public function test_crea_una_fuente(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.fuentes.store'), [
                'titulo' => 'Diccionario Mam',
                'institucion' => 'ALMG',
                'anio' => 2011,
                'licencia' => 'Reproducción sin fines comerciales citando la fuente',
            ])
            ->assertCreated()
            ->assertJsonPath('data.institucion', 'ALMG');
    }

    /**
     * El título es una cita y se guarda tal como está impreso: aquí **no** corre el
     * normalizador del Mam, a diferencia de `entradas.mam` y `categorias.nombre_mam`.
     */
    public function test_el_titulo_se_guarda_tal_cual_esta_impreso(): void
    {
        $titulo = "Tu'jil Qyol Mam te Tkab'in Yol";

        $this->actingAs($this->editor())
            ->postJson(route('admin.fuentes.store'), ['titulo' => $titulo])
            ->assertCreated()
            ->assertJsonPath('data.titulo', $titulo);

        $this->assertDatabaseHas('fuentes', ['titulo' => $titulo]);
    }

    public function test_exige_titulo(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.fuentes.store'), ['institucion' => 'ALMG'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('titulo');
    }

    public function test_rechaza_un_ano_en_el_futuro(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.fuentes.store'), [
                'titulo' => 'Un libro',
                'anio' => (int) date('Y') + 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anio');
    }

    public function test_edita_una_fuente(): void
    {
        $fuente = Fuente::factory()->create();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.fuentes.update', $fuente), ['anio' => 2016])
            ->assertOk()
            ->assertJsonPath('data.anio', 2016);
    }

    /**
     * Perder la trazabilidad de unas palabras es grave; perder las palabras lo sería mucho
     * más. La FK es `nullOnDelete`.
     */
    public function test_borrar_una_fuente_no_borra_sus_entradas(): void
    {
        $fuente = Fuente::factory()->create();
        $entrada = Entrada::factory()->create(['fuente_id' => $fuente->id]);

        $this->actingAs(User::factory()->administrador()->create())
            ->deleteJson(route('admin.fuentes.destroy', $fuente))
            ->assertNoContent();

        $this->assertDatabaseHas('entradas', ['id' => $entrada->id, 'fuente_id' => null]);
    }

    /** El catálogo de ALMG se lee para el formulario, pero no se edita desde el panel. */
    public function test_el_catalogo_gramatical_se_lee_completo(): void
    {
        $this->actingAs($this->editor())
            ->getJson(route('admin.categorias-gramaticales.index'))
            ->assertOk()
            ->assertJsonCount(16, 'data')
            ->assertJsonFragment(['abreviatura' => 'pos.', 'nombre' => 'Posicional']);
    }
}
