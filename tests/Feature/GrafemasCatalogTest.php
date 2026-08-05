<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\GrafemasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prueba de humo del arnés de tests, no de la ortografía.
 *
 * Comprueba que la migración de `grafemas` corre contra el esquema MySQL de pruebas
 * (en SQLite no corre: `no such collation sequence`), que el seeder siembra, y que
 * `ẍ` y el saltillo sobreviven el viaje hasta la base de pruebas. Los vectores de
 * tokenización, ordenamiento y normalización de `.claude/rules/ortografia-mam.md`
 * van en su propia suite, junto con el tokenizador.
 */
class GrafemasCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_suite_corre_contra_el_esquema_de_pruebas(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('aprende_mam_testing', DB::connection()->getDatabaseName());
    }

    public function test_el_catalogo_tiene_los_32_grafemas_en_orden(): void
    {
        $this->seed(GrafemasSeeder::class);

        $this->assertSame(32, DB::table('grafemas')->count());
        $this->assertSame(
            range(1, 32),
            DB::table('grafemas')->orderBy('posicion')->pluck('posicion')->all()
        );
    }

    /** Los dos grafemas que se corrompen sin dejar rastro visible. */
    public function test_los_grafemas_no_ascii_sobreviven_el_viaje_a_la_base(): void
    {
        $this->seed(GrafemasSeeder::class);

        $grafema = fn (int $posicion): string => DB::table('grafemas')
            ->where('posicion', $posicion)
            ->value('grafema');

        // Comparación por punto de código: en pantalla `ẍ` y `x` se parecen.
        $this->assertSame("\u{1E8D}", $grafema(30), 'La posición 30 debe ser ẍ (U+1E8D), no x ni õ.');
        $this->assertSame("\u{2019}", $grafema(32), 'El saltillo debe ser U+2019, no el apóstrofo recto U+0027.');
    }
}
