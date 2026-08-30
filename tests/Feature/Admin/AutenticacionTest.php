<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceso al panel: sesión del grupo `web`, mismo origen que la API.
 */
final class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_manda_al_panel(): void
    {
        // Este dominio no sirve contenido público: el sitio de estudiantes es otro.
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_el_panel_exige_sesion(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_acceso_se_ve_sin_sesion(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Panel de administración');
    }

    public function test_se_entra_con_credenciales_validas(): void
    {
        $usuario = User::factory()->create(['email' => 'docente@ejemplo.gt']);

        $this->post(route('login'), [
            'email' => 'docente@ejemplo.gt',
            'password' => 'password',
        ])->assertRedirect(route('admin.panel'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_no_se_entra_con_la_contrasena_equivocada(): void
    {
        User::factory()->create(['email' => 'docente@ejemplo.gt']);

        $this->post(route('login'), [
            'email' => 'docente@ejemplo.gt',
            'password' => 'la-que-no-es',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * El límite de intentos que pide la Especificación Técnica.
     *
     * Lo que se comprueba no es que falle el sexto intento equivocado, sino que falle el
     * sexto **acertado**: si la contraseña correcta pasara igual, el límite no estaría
     * frenando nada.
     */
    public function test_limita_los_intentos_de_acceso(): void
    {
        User::factory()->create(['email' => 'docente@ejemplo.gt']);

        foreach (range(1, 5) as $intento) {
            $this->post(route('login'), [
                'email' => 'docente@ejemplo.gt',
                'password' => 'la-que-no-es',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => 'docente@ejemplo.gt',
            'password' => 'password',
        ])->assertSessionHasErrorsIn('default', ['email']);

        $this->assertGuest();

        $errores = session('errors')->get('email');
        $this->assertStringContainsString('Demasiados intentos', $errores[0]);
    }

    /** El contador va por correo más origen, no solo por origen. */
    public function test_el_limite_no_deja_fuera_a_los_demas_usuarios(): void
    {
        User::factory()->create(['email' => 'uno@ejemplo.gt']);
        $otra = User::factory()->create(['email' => 'dos@ejemplo.gt']);

        foreach (range(1, 6) as $intento) {
            $this->post(route('login'), [
                'email' => 'uno@ejemplo.gt',
                'password' => 'la-que-no-es',
            ]);
        }

        $this->post(route('login'), [
            'email' => 'dos@ejemplo.gt',
            'password' => 'password',
        ])->assertRedirect(route('admin.panel'));

        $this->assertAuthenticatedAs($otra);
    }

    /**
     * El cascarón ya no pinta el nombre ni el rol: eso lo dibuja React con lo que devuelve
     * `GET /yo`, y de eso da fe `test_la_api_del_panel_dice_quien_entro`.
     *
     * Lo que sí tiene que traer esta vista son las dos cosas que el panel no se puede dar a
     * sí mismo, y sin las cuales falla de una forma que no señala la causa: el contenedor
     * donde monta —sin él la página queda en blanco, sin error— y el token CSRF —sin él la
     * primera escritura responde 419.
     */
    public function test_el_panel_se_ve_con_sesion(): void
    {
        $usuario = User::factory()->administrador()->create(['name' => 'María López']);

        $this->withoutVite()
            ->actingAs($usuario)
            ->get('/admin')
            ->assertOk()
            ->assertSee('id="panel"', escape: false)
            ->assertSee('name="csrf-token"', escape: false);
    }

    public function test_se_sale_de_la_sesion(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** Las rutas del panel responden JSON, no una redirección a un formulario HTML. */
    public function test_la_api_del_panel_responde_401_sin_sesion(): void
    {
        $this->getJson('/api/v1/admin/entradas')->assertUnauthorized();
    }

    public function test_la_api_del_panel_dice_quien_entro(): void
    {
        $usuario = User::factory()->editor()->create(['name' => 'Ana Pérez']);

        $this->actingAs($usuario)
            ->getJson(route('admin.yo'))
            ->assertOk()
            ->assertJson([
                'nombre' => 'Ana Pérez',
                'rol' => 'editor',
                'es_administrador' => false,
            ]);
    }
}
