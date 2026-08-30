<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\CategoriaGramatical;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * El CRUD de entradas visto desde el panel.
 *
 * El mutator ya tiene su propia suite en `tests/Feature/Mam`; aquí se comprueba que el
 * camino HTTP no lo esquive y que la validación diga lo que tiene que decir.
 */
final class EntradasTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    private function editor(): User
    {
        return User::factory()->editor()->create();
    }

    /**
     * El caso de la guía operativa: el editor pega texto de un PDF antiguo, con la `ẍ`
     * corrompida en `õ` y el apóstrofo recto del teclado. El panel lo guarda bien sin
     * decirle nada, que es exactamente lo que el Manual de Normas le promete.
     */
    public function test_guarda_una_entrada_pegada_de_un_pdf_corrupto(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.entradas.store'), [
                'mam' => "õiky q'aq'",
                'espanol' => 'conejo; fuego',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mam', 'ẍiky q’aq’');

        $fila = DB::table('entradas')->first();

        // Comparación por punto de código: en pantalla `ẍ` y `x` se parecen.
        $this->assertSame('ẍiky q’aq’', $fila->mam);
        $this->assertSame('xiky qaq', $fila->busqueda);
        $this->assertStringStartsWith('3006', $fila->orden_alfabetico);
    }

    public function test_una_entrada_nueva_nace_sin_revisar(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'chmol',
                'espanol' => 'reunión',
            ])
            ->assertCreated()
            ->assertJsonPath('data.revisado', false);
    }

    /** Las derivadas se calculan; que un request las traiga es un error de programa. */
    public function test_rechaza_las_columnas_derivadas(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'chmol',
                'espanol' => 'reunión',
                'busqueda' => 'inventada',
                'orden_alfabetico' => '999999',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['busqueda', 'orden_alfabetico']);

        $this->assertDatabaseCount('entradas', 0);
    }

    public function test_rechaza_la_bandera_de_revision_en_el_formulario(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'chmol',
                'espanol' => 'reunión',
                'revisado' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('revisado');
    }

    public function test_exige_lema_y_traduccion(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.entradas.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mam', 'espanol']);
    }

    /** El listado se ordena por la columna derivada, nunca por `mam`. */
    public function test_el_listado_sale_en_orden_mam(): void
    {
        foreach (['xaq', 'ẍiky', 'chmol', 'b’aq', 'ch’el'] as $lema) {
            Entrada::factory()->conLema($lema)->create();
        }

        $this->actingAs($this->editor())
            ->getJson(route('admin.entradas.index'))
            ->assertOk()
            ->assertJsonPath('data.*.mam', ['b’aq', 'chmol', 'ch’el', 'xaq', 'ẍiky']);
    }

    /**
     * Quien busca teclea lo que puede: ni el saltillo ni la diéresis están en su teclado.
     * La búsqueda pasa el término por la misma función que generó la columna.
     */
    public function test_encuentra_sin_escribir_el_saltillo(): void
    {
        Entrada::factory()->conLema('q’aq’')->create();
        Entrada::factory()->conLema('chmol')->create();

        foreach (['qaq', "q'aq'", 'Q’AQ’'] as $termino) {
            $this->actingAs($this->editor())
                ->getJson(route('admin.entradas.index', ['buscar' => $termino]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.mam', 'q’aq’');
        }
    }

    public function test_encuentra_sin_escribir_la_dieresis(): void
    {
        Entrada::factory()->conLema('ẍiky')->create();

        $this->actingAs($this->editor())
            ->getJson(route('admin.entradas.index', ['buscar' => 'xiky']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filtra_por_estado_de_revision(): void
    {
        Entrada::factory()->conLema('chmol')->revisada()->create();
        Entrada::factory()->conLema('b’aq')->create();

        $this->actingAs($this->editor())
            ->getJson(route('admin.entradas.index', ['revisado' => '1']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mam', 'chmol');
    }

    /**
     * Filtrar por fuente es cómo se revisa lo que se acaba de transcribir: se carga de un
     * libro por vez y esto devuelve justo esa tanda.
     */
    public function test_filtra_por_fuente(): void
    {
        $colimam = Fuente::factory()->create(['titulo' => 'Diccionario COLIMAM']);
        $otra = Fuente::factory()->create(['titulo' => 'Gramática pedagógica']);

        Entrada::factory()->conLema('chmol')->create(['fuente_id' => $colimam->id]);
        Entrada::factory()->conLema('b’aq')->create(['fuente_id' => $otra->id]);

        $this->actingAs($this->editor())
            ->getJson(route('admin.entradas.index', ['fuente' => $colimam->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mam', 'chmol');
    }

    public function test_filtra_las_que_no_tienen_fuente(): void
    {
        $fuente = Fuente::factory()->create();

        Entrada::factory()->conLema('chmol')->create(['fuente_id' => $fuente->id]);
        Entrada::factory()->conLema('b’aq')->create(['fuente_id' => null]);

        $this->actingAs($this->editor())
            ->getJson(route('admin.entradas.index', ['fuente' => 'ninguna']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mam', 'b’aq');
    }

    /**
     * El Manual de Normas pide que toda entrada lleve al menos un tema antes de publicarse.
     * Sin este filtro, entre seis mil no hay forma de encontrar las que faltan.
     */
    public function test_filtra_las_que_no_tienen_tema(): void
    {
        $animales = Categoria::factory()->create(['slug' => 'animales']);

        $conTema = Entrada::factory()->conLema('chmol')->create();
        $conTema->categorias()->sync([$animales->id]);

        Entrada::factory()->conLema('b’aq')->create();

        $this->actingAs($this->editor())
            ->getJson(route('admin.entradas.index', ['categoria' => 'ninguna']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mam', 'b’aq');
    }

    public function test_asigna_temas_clase_de_palabra_y_fuente(): void
    {
        $animales = Categoria::factory()->create(['slug' => 'animales']);
        $mercado = Categoria::factory()->create(['slug' => 'el-mercado']);
        $clase = CategoriaGramatical::query()->where('abreviatura', 's.')->firstOrFail();
        $fuente = Fuente::factory()->create();

        $this->actingAs($this->editor())
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'ẍiky',
                'espanol' => 'conejo',
                'categoria_gramatical_id' => $clase->id,
                'fuente_id' => $fuente->id,
                'pagina_fuente' => '112-113',
                'categorias' => [$animales->id, $mercado->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.categoria_gramatical.abreviatura', 's.')
            ->assertJsonCount(2, 'data.categorias');

        $this->assertDatabaseCount('entrada_categoria', 2);
    }

    /**
     * El Manual de Normas dice que una palabra se puede guardar incompleta y terminarse
     * después. Sin fuente ni tema tiene que entrar igual.
     */
    public function test_guarda_una_entrada_incompleta(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'jal',
                'espanol' => 'mazorca',
            ])
            ->assertCreated();
    }

    /** Una edición que no menciona los temas no puede dejar la entrada sin ninguno. */
    public function test_la_edicion_parcial_conserva_los_temas(): void
    {
        $categoria = Categoria::factory()->create();
        $entrada = Entrada::factory()->create();
        $entrada->categorias()->sync([$categoria->id]);

        $this->actingAs($this->editor())
            ->patchJson(route('admin.entradas.update', $entrada), ['espanol' => 'otra cosa'])
            ->assertOk();

        $this->assertSame(1, $entrada->fresh()->categorias()->count());
    }

    public function test_la_edicion_puede_vaciar_los_temas_si_los_menciona(): void
    {
        $categoria = Categoria::factory()->create();
        $entrada = Entrada::factory()->create();
        $entrada->categorias()->sync([$categoria->id]);

        $this->actingAs($this->editor())
            ->patchJson(route('admin.entradas.update', $entrada), ['categorias' => []])
            ->assertOk();

        $this->assertSame(0, $entrada->fresh()->categorias()->count());
    }

    public function test_editar_el_lema_recalcula_las_derivadas(): void
    {
        $entrada = Entrada::factory()->conLema('chmol')->create();

        $this->actingAs($this->editor())
            ->patchJson(route('admin.entradas.update', $entrada), ['mam' => "tz'is"])
            ->assertOk()
            ->assertJsonPath('data.mam', 'tz’is')
            ->assertJsonPath('data.busqueda', 'tzis')
            ->assertJsonPath('data.orden_alfabetico', '260620');
    }

    public function test_rechaza_una_fuente_que_no_existe(): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'jal',
                'espanol' => 'mazorca',
                'fuente_id' => 9999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fuente_id');
    }
}
