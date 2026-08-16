<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * CRUD de temas del vocabulario.
 */
final class CategoriasTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    private function editor(): User
    {
        return User::factory()->editor()->create();
    }

    public function test_crea_un_tema(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.categorias.store'), [
                'nombre_es' => 'Animales',
                'nombre_mam' => 'Txkup',
                'slug' => 'animales',
                'orden' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'animales');
    }

    /** `nombre_mam` es texto en Mam: pasa por el normalizador igual que un lema. */
    public function test_normaliza_el_nombre_en_mam(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.categorias.store'), [
                'nombre_es' => 'Peces',
                'nombre_mam' => 'Kyiõ',
                'slug' => 'peces',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre_mam', 'Kyiẍ');
    }

    public function test_exige_una_direccion_apta_para_url(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.categorias.store'), [
                'nombre_es' => 'Medicina natural',
                'slug' => 'Medicina Natural',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_no_admite_dos_temas_con_la_misma_direccion(): void
    {
        Categoria::factory()->create(['slug' => 'animales']);

        $this->actingAs($this->editor())
            ->postJson(route('admin.categorias.store'), [
                'nombre_es' => 'Otros animales',
                'slug' => 'animales',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    /** Al editar, su propia dirección no puede chocar consigo misma. */
    public function test_editar_un_tema_sin_cambiar_su_direccion(): void
    {
        $categoria = Categoria::factory()->create(['slug' => 'animales']);

        $this->actingAs($this->editor())
            ->patchJson(route('admin.categorias.update', $categoria), [
                'nombre_es' => 'Animales del monte',
                'slug' => 'animales',
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre_es', 'Animales del monte');
    }

    public function test_un_tema_no_puede_ser_su_propio_padre(): void
    {
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.categorias.update', $categoria), [
                'padre_id' => $categoria->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('padre_id');
    }

    public function test_el_listado_trae_el_conteo_de_entradas(): void
    {
        $categoria = Categoria::factory()->create();
        Entrada::factory()->count(2)->create()->each(
            fn (Entrada $entrada) => $entrada->categorias()->sync([$categoria->id])
        );

        $this->actingAs($this->editor())
            ->getJson(route('admin.categorias.index'))
            ->assertOk()
            ->assertJsonPath('data.0.total_entradas', 2);
    }

    /**
     * Borrar un tema no puede llevarse las palabras. Se pierde la clasificación, que es
     * reversible; perder el léxico no lo sería.
     */
    public function test_borrar_un_tema_no_borra_sus_entradas(): void
    {
        $categoria = Categoria::factory()->create();
        $entrada = Entrada::factory()->create();
        $entrada->categorias()->sync([$categoria->id]);

        $this->actingAs(User::factory()->administrador()->create())
            ->deleteJson(route('admin.categorias.destroy', $categoria))
            ->assertNoContent();

        $this->assertDatabaseHas('entradas', ['id' => $entrada->id]);
        $this->assertDatabaseCount('entrada_categoria', 0);
    }

    /** Los hijos quedan huérfanos con `padre_id` nulo, no borrados en cascada. */
    public function test_borrar_un_tema_padre_deja_vivos_a_los_hijos(): void
    {
        $padre = Categoria::factory()->create();
        $hija = Categoria::factory()->hijaDe($padre)->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->deleteJson(route('admin.categorias.destroy', $padre))
            ->assertNoContent();

        $this->assertDatabaseHas('categorias', ['id' => $hija->id, 'padre_id' => null]);
    }
}
