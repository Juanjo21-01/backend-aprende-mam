<?php

declare(strict_types=1);

namespace Tests\Feature\Mam;

use App\Models\Entrada;
use Database\Seeders\GrafemasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El orden del Mam tiene que ser **total**, no solo correcto.
 *
 * Los homógrafos son legítimos —la misma forma con distinta acepción, distinta clase de
 * palabra o distinta fuente— y comparten clave de intercalación. MySQL no promete ningún
 * orden entre filas empatadas, así que sin un desempate explícito el resultado depende del
 * plan de ejecución, que cambia con el tamaño de la tabla y con los índices.
 *
 * Dos consecuencias, ninguna evidente hasta que el diccionario está cargado: al paginar
 * seis mil entradas una puede salir dos veces o no salir en ninguna página, y la
 * exportación produciría un JSON distinto en cada compilación aunque no cambie nada.
 *
 * Estos tests miran la consulta y no solo el resultado, a propósito: con pocas filas MySQL
 * devuelve los empates en orden de inserción y un test de comportamiento pasaría igual sin
 * el desempate, que es exactamente cómo este fallo llega a producción.
 */
final class OrdenEstableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GrafemasSeeder::class);
    }

    public function test_el_orden_mam_desempata_de_forma_determinista(): void
    {
        $sql = Entrada::query()->enOrdenMam()->toSql();

        $this->assertStringContainsString('order by `orden_alfabetico` asc, `id` asc', $sql);
    }

    public function test_los_homografos_comparten_clave_de_intercalacion(): void
    {
        // La premisa del problema. Si esto dejara de ser cierto, el desempate sobraría.
        foreach (['ave', 'planta', 'instrumento'] as $acepcion) {
            Entrada::create(['mam' => 'ch’el', 'espanol' => $acepcion]);
        }

        $this->assertSame(1, Entrada::query()->distinct()->count('orden_alfabetico'));
        $this->assertSame(3, Entrada::query()->count());
    }

    public function test_paginar_homografos_no_repite_ni_pierde_entradas(): void
    {
        foreach (range(1, 7) as $numero) {
            Entrada::create(['mam' => 'ch’el', 'espanol' => "acepción {$numero}"]);
        }

        $vistos = [];

        foreach (range(1, 4) as $pagina) {
            $vistos = array_merge(
                $vistos,
                Entrada::query()->enOrdenMam()->paginate(2, ['*'], 'page', $pagina)->pluck('id')->all()
            );
        }

        $this->assertSame(7, count($vistos), 'Ninguna entrada se pierde al paginar.');
        $this->assertSame($vistos, array_unique($vistos), 'Ninguna entrada sale dos veces.');
    }

    /**
     * La exportación usa el mismo scope. Que dos lecturas seguidas den el mismo orden es lo
     * que evita que cada compilación del sitio genere un artefacto distinto sin motivo.
     */
    public function test_la_exportacion_ordena_igual_en_lecturas_sucesivas(): void
    {
        foreach (range(1, 5) as $numero) {
            Entrada::create(['mam' => 'ch’el', 'espanol' => "acepción {$numero}"]);
        }

        $primera = Entrada::query()->enOrdenMam()->pluck('id')->all();
        $segunda = Entrada::query()->enOrdenMam()->pluck('id')->all();

        $this->assertSame($primera, $segunda);
    }

    /** El desempate no puede alterar el orden alfabético del Mam, que es lo principal. */
    public function test_el_desempate_no_altera_el_orden_del_alfabeto(): void
    {
        foreach (['chmol', 'b’aq', 'tz’is', 'jal', 'ch’el', 'kyaje', 'tzaj', 'k’um', 'ẍiky', 'xaq'] as $lema) {
            Entrada::create(['mam' => $lema, 'espanol' => 'prueba']);
        }

        $this->assertSame(
            ['b’aq', 'chmol', 'ch’el', 'jal', 'k’um', 'kyaje', 'tzaj', 'tz’is', 'xaq', 'ẍiky'],
            Entrada::query()->enOrdenMam()->pluck('mam')->all()
        );
    }
}
