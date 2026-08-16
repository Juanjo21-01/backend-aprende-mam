<?php

declare(strict_types=1);

namespace Tests\Feature\Mam;

use App\Models\Categoria;
use Database\Seeders\GrafemasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `categorias.nombre_mam` es el otro campo en idioma Mam del esquema, y le aplica la misma
 * regla que a `entradas.mam`: no entra a la base sin pasar por el normalizador.
 *
 * No lleva columnas derivadas —los temas los ordena el docente por `orden`, no el alfabeto—,
 * así que aquí solo se comprueba el saneamiento.
 */
final class CategoriaMutatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GrafemasSeeder::class);
    }

    public function test_normaliza_el_nombre_en_mam(): void
    {
        $categoria = Categoria::create([
            'nombre_es' => 'Animales',
            'nombre_mam' => "Txkup q'a",
            'slug' => 'animales',
        ]);

        $this->assertSame(
            'Txkup q’a',
            DB::table('categorias')->where('id', $categoria->id)->value('nombre_mam')
        );
    }

    public function test_corrige_la_corrupcion_de_los_pdf_en_el_nombre_en_mam(): void
    {
        $categoria = Categoria::create([
            'nombre_es' => 'Peces',
            'nombre_mam' => 'Kyiõ',
            'slug' => 'peces',
        ]);

        $this->assertSame('Kyiẍ', $categoria->fresh()->nombre_mam);
    }

    /** El campo es anulable: un tema puede cargarse antes de tener su nombre en Mam. */
    public function test_tolera_el_nombre_en_mam_ausente(): void
    {
        $categoria = Categoria::create([
            'nombre_es' => 'Colores',
            'slug' => 'colores',
        ]);

        $this->assertNull($categoria->fresh()->nombre_mam);
    }
}
