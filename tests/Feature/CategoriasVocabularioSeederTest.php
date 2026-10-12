<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\VersionContenido;
use Database\Seeders\CategoriasVocabularioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo de temas contra el que el importador clasifica el vocabulario.
 *
 * Tiene que poder correrse en producción encima de lo que ya cargó el docente: crea lo que
 * falta y no toca nada más.
 */
final class CategoriasVocabularioSeederTest extends TestCase
{
    use RefreshDatabase;

    private const SLUGS = [
        'animales', 'la-familia', 'colores', 'numeros', 'la-naturaleza', 'la-casa', 'alimentos',
        'el-cuerpo', 'ropa-y-tejido', 'la-escuela', 'oficios-y-trabajo', 'plantas-y-cultivos',
        'tiempo-y-calendario', 'la-comunidad', 'acciones', 'cualidades',
    ];

    public function test_crea_los_dieciseis_temas_de_primer_nivel_en_su_orden(): void
    {
        $this->seed(CategoriasVocabularioSeeder::class);

        $temas = Categoria::query()->enOrdenDelPanel()->get();

        $this->assertSame(self::SLUGS, $temas->pluck('slug')->all());
        $this->assertSame(range(1, 16), $temas->pluck('orden')->all());
        $this->assertTrue($temas->every(fn (Categoria $tema) => $tema->padre_id === null));
    }

    /**
     * Los nombres en Mam los confirma el validador en el panel. Sembrarlos aquí publicaría Mam
     * que nadie revisó, y las categorías no tienen bandera de revisión que lo frene.
     */
    public function test_no_inventa_nombres_en_mam(): void
    {
        $this->seed(CategoriasVocabularioSeeder::class);

        $this->assertSame(0, Categoria::query()->whereNotNull('nombre_mam')->count());
    }

    public function test_es_idempotente(): void
    {
        $this->seed(CategoriasVocabularioSeeder::class);
        $this->seed(CategoriasVocabularioSeeder::class);

        $this->assertSame(16, Categoria::query()->count());
    }

    /** Lo que el docente ya cargó o corrigió en el panel manda sobre el seeder. */
    public function test_no_pisa_lo_editado_en_el_panel(): void
    {
        Categoria::query()->create([
            'slug' => 'animales',
            'nombre_es' => 'Los animales',
            'nombre_mam' => 'txkup',
            'orden' => 9,
        ]);

        $this->seed(CategoriasVocabularioSeeder::class);

        $animales = Categoria::query()->where('slug', 'animales')->sole();

        $this->assertSame('Los animales', $animales->nombre_es);
        $this->assertSame('txkup', $animales->nombre_mam);
        $this->assertSame(9, $animales->orden);
        $this->assertSame(16, Categoria::query()->count());
    }

    /**
     * El catálogo de temas sale completo en la exportación, así que un tema nuevo es contenido
     * nuevo. Sin eventos de modelo para no subir dieciséis veces; una sola al final, y ninguna
     * si no hubo nada que crear.
     */
    public function test_sube_la_version_una_vez_y_solo_si_creo_algo(): void
    {
        $inicial = VersionContenido::numeroActual();

        $this->seed(CategoriasVocabularioSeeder::class);
        $this->assertSame($inicial + 1, VersionContenido::numeroActual());

        $this->seed(CategoriasVocabularioSeeder::class);
        $this->assertSame($inicial + 1, VersionContenido::numeroActual());
    }
}
