<?php

declare(strict_types=1);

namespace Tests\Feature\Publicacion;

use App\Jobs\PublishSite;
use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\User;
use App\Models\VersionContenido;
use App\Support\Publishing\DeployHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * El puente entre este backend y el repositorio de Astro.
 *
 * Lo que se prueba, sobre todo, es el debounce: cargar cuarenta palabras seguidas tiene que
 * producir **una** compilación del sitio y no cuarenta. En un cPanel compartido, cuarenta
 * compilaciones seguidas no son un derroche: son una cola que no avanza.
 */
final class PublicacionTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    private const HOOK = 'https://api.netlify.com/build_hooks/pruebaDeColeccion';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aprendemam.publicacion.deploy_hook_url' => self::HOOK,
            'aprendemam.publicacion.habilitada' => true,
            'aprendemam.publicacion.retardo_segundos' => 300,
        ]);

        // Red de seguridad para toda la clase: la cola en pruebas es síncrona, así que un
        // guardado hecho antes de `Queue::fake()` ejecuta el trabajo en el acto y saldría a
        // internet de verdad.
        //
        // Va `preventStrayRequests()` y no un `Http::fake()` general por dos motivos: el
        // segundo taparía el olvido en lugar de delatarlo, y además Laravel resuelve el
        // primer stub que coincida, así que un `fake()` puesto acá ganaría sobre el que
        // declare un test para simular una respuesta de error.
        Http::preventStrayRequests();
    }

    // --- Qué dispara una publicación ------------------------------------------------

    public function test_guardar_una_entrada_programa_la_publicacion(): void
    {
        Queue::fake();

        Entrada::factory()->create();

        Queue::assertPushed(PublishSite::class, 1);
    }

    /** Las tres entidades salen en el JSON exportado, así que las tres publican. */
    public function test_las_categorias_y_las_fuentes_tambien_publican(): void
    {
        Queue::fake();

        Categoria::factory()->create();
        Fuente::factory()->create();

        Queue::assertPushed(PublishSite::class, 2);
    }

    public function test_borrar_tambien_publica(): void
    {
        // El alta corre antes de `Queue::fake()`, así que su trabajo se ejecuta en el acto.
        Http::fake();

        $entrada = Entrada::factory()->create();

        Queue::fake();

        $entrada->delete();

        Queue::assertPushed(PublishSite::class);
    }

    public function test_la_publicacion_espera_el_retardo(): void
    {
        Queue::fake();

        Entrada::factory()->create();

        Queue::assertPushed(function (PublishSite $trabajo): bool {
            return $trabajo->delay?->getTimestamp() >= now()->addSeconds(290)->getTimestamp();
        });
    }

    // --- El debounce ------------------------------------------------------------------

    /**
     * El corazón del asunto. Cada guardado encola su propio trabajo, pero solo el último de
     * la tanda encuentra su testigo intacto; los anteriores se retiran sin avisar a nadie.
     */
    public function test_solo_el_ultimo_guardado_de_la_tanda_publica(): void
    {
        Http::fake();
        Queue::fake();

        // Una sesión de carga: tres palabras seguidas.
        Entrada::factory()->count(3)->create();

        $encolados = [];
        Queue::assertPushed(PublishSite::class, function (PublishSite $trabajo) use (&$encolados): bool {
            $encolados[] = $trabajo;

            return true;
        });

        $this->assertCount(3, $encolados, 'Cada guardado encola su trabajo.');

        // Ahora corren, en el orden en que se encolaron.
        foreach ($encolados as $trabajo) {
            $trabajo->handle(app(DeployHook::class));
        }

        // Pero solo uno llegó a avisar al proveedor.
        Http::assertSentCount(1);
    }

    public function test_un_trabajo_sustituido_no_avisa_a_nadie(): void
    {
        Http::fake();
        Queue::fake();

        Entrada::factory()->create();

        $primero = null;
        Queue::assertPushed(PublishSite::class, function (PublishSite $trabajo) use (&$primero): bool {
            $primero ??= $trabajo;

            return true;
        });

        // Alguien guarda otra palabra antes de que venza el retardo del primero.
        Entrada::factory()->create();

        $primero->handle(app(DeployHook::class));

        Http::assertNothingSent();
    }

    // --- Cuándo NO se publica ---------------------------------------------------------

    /**
     * En desarrollo no hay a quién avisar. Encolar trabajos que no van a hacer nada solo
     * ensucia la cola, y peor: una copia local con el hook de producción dispararía
     * compilaciones reales cada vez que alguien prueba algo.
     */
    public function test_sin_deploy_hook_no_se_encola_nada(): void
    {
        config(['aprendemam.publicacion.deploy_hook_url' => '']);

        Queue::fake();

        Entrada::factory()->create();

        Queue::assertNothingPushed();
    }

    public function test_con_la_publicacion_apagada_no_se_encola_nada(): void
    {
        config(['aprendemam.publicacion.habilitada' => false]);

        Queue::fake();

        Entrada::factory()->create();

        Queue::assertNothingPushed();
    }

    /**
     * El camino de la importación del corpus: 6,185 entradas no pueden encolar 6,185
     * trabajos ni disparar una compilación por palabra.
     */
    public function test_una_importacion_sin_eventos_no_publica(): void
    {
        Queue::fake();

        Entrada::withoutEvents(function (): void {
            Entrada::factory()->count(5)->create();
        });

        Queue::assertNothingPushed();
        $this->assertSame(5, Entrada::query()->count());
    }

    // --- El aviso al proveedor --------------------------------------------------------

    public function test_avisa_al_proveedor_con_el_numero_de_version(): void
    {
        Http::fake();

        app(DeployHook::class)->trigger(42);

        Http::assertSent(function ($peticion): bool {
            return $peticion->url() === self::HOOK
                && $peticion->method() === 'POST'
                && str_contains($peticion['trigger_title'], 'v42');
        });
    }

    public function test_el_trabajo_publica_la_version_vigente(): void
    {
        Http::fake();

        Entrada::factory()->create();
        $version = VersionContenido::numeroActual();

        (new PublishSite)->handle(app(DeployHook::class));

        Http::assertSent(fn ($peticion): bool => str_contains($peticion['trigger_title'], "v{$version}"));
    }

    /**
     * Una publicación que falla en silencio es contenido que el docente cree publicado. El
     * cliente lanza para que el trabajo lo reintente y, agotados los reintentos, quede
     * registrado en `failed_jobs`.
     */
    public function test_un_fallo_del_proveedor_no_pasa_desapercibido(): void
    {
        Http::fake(fn () => Http::response('nope', 503));

        $this->expectExceptionMessageMatches('/deploy hook respondió 503/');

        app(DeployHook::class)->trigger(7);
    }

    public function test_sin_hook_configurado_el_cliente_no_llama_a_nadie(): void
    {
        config(['aprendemam.publicacion.deploy_hook_url' => '']);

        Http::fake();

        app(DeployHook::class)->trigger(7);

        Http::assertNothingSent();
    }

    // --- El comando manual ------------------------------------------------------------

    public function test_el_comando_encola_una_publicacion(): void
    {
        Queue::fake();

        $this->artisan('mam:publicar')->assertSuccessful();

        Queue::assertPushed(PublishSite::class);
    }

    public function test_el_comando_puede_avisar_en_el_momento(): void
    {
        Http::fake();

        $this->artisan('mam:publicar', ['--ahora' => true])->assertSuccessful();

        Http::assertSentCount(1);
    }

    /**
     * La orden manual no lleva testigo: no es una tanda de guardados sino una decisión
     * explícita, así que no tiene que retirarse aunque haya un trabajo con debounce
     * esperando su turno.
     */
    public function test_el_comando_publica_aunque_haya_una_tanda_en_curso(): void
    {
        Http::fake();

        // `Queue::fake()` para que el guardado deje el testigo pendiente sin llegar a
        // ejecutar su propio trabajo; lo que se mide es el aviso del trabajo sin testigo.
        Queue::fake();

        Entrada::factory()->create();

        (new PublishSite)->handle(app(DeployHook::class));

        Http::assertSentCount(1);
    }

    public function test_el_comando_falla_sin_hook_configurado(): void
    {
        config(['aprendemam.publicacion.deploy_hook_url' => '']);

        $this->artisan('mam:publicar')
            ->expectsOutputToContain('No hay DEPLOY_HOOK_URL configurado')
            ->assertFailed();
    }

    // --- La versión -------------------------------------------------------------------

    /**
     * La versión sube al guardar, no al publicar. Así `GET /export/version` dice la verdad
     * aunque la publicación esté todavía esperando su retardo.
     */
    public function test_la_version_sube_al_guardar_aunque_no_se_haya_publicado(): void
    {
        Queue::fake();

        $antes = VersionContenido::numeroActual();

        Entrada::factory()->create();

        $this->assertGreaterThan($antes, VersionContenido::numeroActual());
        Queue::assertPushed(PublishSite::class);
    }

    /** Guardar desde el panel es un camino más al mismo sitio. */
    public function test_guardar_desde_el_panel_tambien_programa_la_publicacion(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->editor()->create())
            ->postJson(route('admin.entradas.store'), [
                'mam' => 'chmol',
                'espanol' => 'reunión',
            ])
            ->assertCreated();

        Queue::assertPushed(PublishSite::class);
    }
}
