<?php

declare(strict_types=1);

namespace Tests\Feature\Publicacion;

use App\Models\Entrada;
use App\Models\User;
use App\Models\VersionContenido;
use App\Support\Publishing\DeployHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsCatalogs;
use Tests\TestCase;

/**
 * Lo que el panel enseña para contestar «lo que guardé, ¿ya está en el sitio?».
 *
 * Antes esa pregunta no se contestaba en ninguna parte: había que entrar al servidor a leer
 * el log. Quien carga vocabulario no es programador.
 */
final class EstadoPublicacionTest extends TestCase
{
    use RefreshDatabase, SeedsCatalogs;

    private const HOOK = 'https://api.netlify.com/build_hooks/pruebaDeEstado';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function conHook(): void
    {
        config([
            'aprendemam.publicacion.deploy_hook_url' => self::HOOK,
            'aprendemam.publicacion.habilitada' => true,
            'aprendemam.publicacion.retardo_segundos' => 300,
        ]);
    }

    public function test_sin_sesion_no_se_puede_consultar(): void
    {
        $this->getJson(route('admin.publicacion'))->assertUnauthorized();
    }

    /** Lo leen los dos roles: quien carga vocabulario también quiere saber si salió. */
    public function test_un_editor_tambien_lo_puede_consultar(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->getJson(route('admin.publicacion'))
            ->assertOk();
    }

    /**
     * Recién instalado no se ha publicado nada, y eso se dice con `null`, no con un cero.
     * Un cero se leería como «se publicó la versión cero».
     */
    public function test_una_instalacion_nueva_dice_que_nunca_se_publico(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->getJson(route('admin.publicacion'))
            ->assertOk()
            ->assertJson([
                'version_publicada' => null,
                'publicado_en' => null,
                'sin_publicar' => true,
            ]);
    }

    /**
     * Sin deploy hook no hay a quién avisar. El panel tiene que decirlo en vez de dejar
     * «pendiente» para siempre, que parecería una publicación que tarda en llegar.
     */
    public function test_sin_deploy_hook_avisa_que_no_esta_configurada(): void
    {
        config(['aprendemam.publicacion.deploy_hook_url' => '']);

        $this->actingAs(User::factory()->administrador()->create())
            ->getJson(route('admin.publicacion'))
            ->assertOk()
            ->assertJson(['habilitada' => false, 'programada_para' => null]);
    }

    public function test_guardar_contenido_deja_una_publicacion_programada(): void
    {
        $this->conHook();
        Queue::fake();

        Entrada::factory()->create();

        $respuesta = $this->actingAs(User::factory()->administrador()->create())
            ->getJson(route('admin.publicacion'))
            ->assertOk()
            ->assertJson(['habilitada' => true, 'sin_publicar' => true]);

        $this->assertNotNull(
            $respuesta->json('programada_para'),
            'Con un guardado reciente el panel tiene que poder decir para cuándo queda.'
        );
    }

    /**
     * El caso que da sentido a todo lo demás: después de publicar de verdad, el panel dice
     * «al día» y con qué versión.
     */
    public function test_tras_publicar_el_panel_dice_que_esta_al_dia(): void
    {
        $this->conHook();
        Http::fake([self::HOOK => Http::response('', 200)]);

        Entrada::factory()->create();

        $version = VersionContenido::numeroActual();
        app(DeployHook::class)->trigger($version);

        $this->actingAs(User::factory()->administrador()->create())
            ->getJson(route('admin.publicacion'))
            ->assertOk()
            ->assertJson([
                'version' => $version,
                'version_publicada' => $version,
                'sin_publicar' => false,
                'programada_para' => null,
            ]);
    }

    /**
     * Guardar después de publicar vuelve a dejar el sitio atrasado. Es el estado normal de
     * una sesión de carga, y el que más veces va a ver quien usa el panel.
     */
    public function test_guardar_despues_de_publicar_vuelve_a_dejar_cambios_pendientes(): void
    {
        $this->conHook();
        Http::fake([self::HOOK => Http::response('', 200)]);

        app(DeployHook::class)->trigger(VersionContenido::numeroActual());

        Queue::fake();
        Entrada::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->getJson(route('admin.publicacion'))
            ->assertOk()
            ->assertJson(['sin_publicar' => true]);
    }

    /**
     * Una publicación que falla no debe dejar rastro de éxito: el docente vería «al día»
     * mientras el sitio sigue sirviendo contenido viejo, que es peor que no saber nada.
     */
    public function test_una_publicacion_fallida_no_se_anota(): void
    {
        $this->conHook();
        Http::fake([self::HOOK => Http::response('', 500)]);

        try {
            app(DeployHook::class)->trigger(VersionContenido::numeroActual());
        } catch (\RuntimeException) {
            // El hook lanza a propósito, para que el trabajo lo reintente.
        }

        $this->assertNull(VersionContenido::actual()->publicado_numero);
    }

    /** Un aviso tardío de una versión vieja no puede pisar a uno más nuevo. */
    public function test_lo_publicado_nunca_retrocede(): void
    {
        VersionContenido::marcarPublicada(10);
        VersionContenido::marcarPublicada(4);

        $this->assertSame(10, VersionContenido::actual()->publicado_numero);
    }
}
