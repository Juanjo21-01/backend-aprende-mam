<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Los tres caminos por los que se fija una contraseña en este sistema.
 *
 * No hay recuperación por correo a propósito: con tres o cuatro cuentas resulta más fiable
 * que un administrador resetee la clave que depender de que el SMTP del alojamiento
 * compartido entregue el mensaje, de que no caiga en spam y de que el destinatario tenga
 * señal para abrirlo. El hueco que deja eso —el administrador único que se queda fuera— lo
 * tapa `php artisan mam:cambiar-contrasena`.
 */
final class ContrasenasTest extends TestCase
{
    use RefreshDatabase;

    // --- Cambiar la propia ----------------------------------------------------------

    public function test_cualquiera_cambia_su_propia_contrasena(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->patchJson(route('admin.yo.contrasena'), [
                'current_password' => 'password',
                'password' => 'unaClaveNueva',
                'password_confirmation' => 'unaClaveNueva',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('unaClaveNueva', $editor->fresh()->password));
    }

    /**
     * La razón de pedir la contraseña actual: un panel abierto y desatendido en la
     * computadora de la escuela no puede servir para dejar fuera a su dueño.
     */
    public function test_no_se_cambia_sin_saber_la_actual(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->patchJson(route('admin.yo.contrasena'), [
                'current_password' => 'la-que-no-es',
                'password' => 'unaClaveNueva',
                'password_confirmation' => 'unaClaveNueva',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $editor->fresh()->password));
    }

    public function test_las_dos_contrasenas_nuevas_tienen_que_coincidir(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.yo.contrasena'), [
                'current_password' => 'password',
                'password' => 'unaClaveNueva',
                'password_confirmation' => 'otraDistinta',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_la_contrasena_nueva_no_puede_ser_la_misma(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.yo.contrasena'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    /**
     * Se comprueba el **mensaje**, no solo que haya error.
     *
     * El proyecto no publica los archivos de idioma de Laravel, así que una regla sin
     * mensaje propio no falla: enseña su clave de traducción. Esta versión del test pasaba
     * en verde mientras el panel mostraba «validation.min.string» a la cara del docente.
     */
    public function test_la_contrasena_nueva_tiene_un_minimo(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.yo.contrasena'), [
                'current_password' => 'password',
                'password' => 'corta',
                'password_confirmation' => 'corta',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'La contraseña necesita al menos 8 caracteres.',
            ]);
    }

    /** La misma exigencia por la otra puerta: el reseteo que hace un administrador. */
    public function test_el_reseteo_tambien_explica_el_minimo(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.usuarios.contrasena', $editor), [
                'password' => 'corta',
                'password_confirmation' => 'corta',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'La contraseña necesita al menos 8 caracteres.',
            ]);
    }

    // --- Reseteo por el administrador -----------------------------------------------

    /** El camino de recuperación: quien olvidó la clave no la sabe, así que no se le pide. */
    public function test_el_administrador_resetea_la_contrasena_de_otro(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.usuarios.contrasena', $editor), [
                'password' => 'claveReseteada',
                'password_confirmation' => 'claveReseteada',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('claveReseteada', $editor->fresh()->password));
    }

    public function test_el_editor_no_resetea_la_contrasena_de_nadie(): void
    {
        $otro = User::factory()->editor()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->patchJson(route('admin.usuarios.contrasena', $otro), [
                'password' => 'claveReseteada',
                'password_confirmation' => 'claveReseteada',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $otro->fresh()->password));
    }

    public function test_el_reseteo_tambien_exige_el_minimo(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.usuarios.contrasena', $editor), [
                'password' => 'corta',
                'password_confirmation' => 'corta',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_despues_del_reseteo_se_entra_con_la_nueva(): void
    {
        $editor = User::factory()->editor()->create(['email' => 'ana@ejemplo.edu.gt']);

        $this->actingAs(User::factory()->administrador()->create())
            ->patchJson(route('admin.usuarios.contrasena', $editor), [
                'password' => 'claveReseteada',
                'password_confirmation' => 'claveReseteada',
            ])
            ->assertOk();

        // Sesión de verdad limpia. No basta con llamar a salir: `actingAs` deja el usuario
        // fijado en el guard del contenedor, así que la siguiente petición seguiría viéndose
        // autenticada. Y con sesión abierta, el middleware `guest` **no procesa el acceso**:
        // rebota con 302 hacia la raíz, que se parece muchísimo a un acceso correcto.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post(route('login'), [
            'email' => 'ana@ejemplo.edu.gt',
            'password' => 'claveReseteada',
        ])->assertRedirect(route('admin.panel'));

        $this->assertAuthenticatedAs($editor);
    }

    // --- El comando de rescate ------------------------------------------------------

    public function test_el_comando_cambia_la_contrasena(): void
    {
        $admin = User::factory()->administrador()->create(['email' => 'solo@ejemplo.edu.gt']);

        $this->artisan('mam:cambiar-contrasena', ['--correo' => 'solo@ejemplo.edu.gt'])
            ->expectsQuestion('Contraseña nueva', 'claveDeRescate')
            ->expectsQuestion('Repetí la contraseña', 'claveDeRescate')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('claveDeRescate', $admin->fresh()->password));
    }

    public function test_el_comando_avisa_si_la_cuenta_no_existe(): void
    {
        $this->artisan('mam:cambiar-contrasena', ['--correo' => 'nadie@ejemplo.edu.gt'])
            ->expectsOutputToContain('No hay ninguna cuenta con el correo')
            ->assertFailed();
    }

    public function test_el_comando_exige_que_las_dos_coincidan(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.edu.gt']);

        $this->artisan('mam:cambiar-contrasena', ['--correo' => 'ana@ejemplo.edu.gt'])
            ->expectsQuestion('Contraseña nueva', 'claveDeRescate')
            ->expectsQuestion('Repetí la contraseña', 'otraDistinta')
            ->expectsOutputToContain('Las dos contraseñas no coinciden.')
            ->assertFailed();

        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
    }

    /**
     * El caso que justifica el comando: el administrador único que olvida su clave y no
     * tiene quién se la resetee desde el panel.
     */
    public function test_el_comando_rescata_al_administrador_unico(): void
    {
        $admin = User::factory()->administrador()->create(['email' => 'unico@ejemplo.edu.gt']);

        $this->assertTrue($admin->isLastActiveAdministrator());

        $this->artisan('mam:cambiar-contrasena', ['--correo' => 'unico@ejemplo.edu.gt'])
            ->expectsQuestion('Contraseña nueva', 'claveDeRescate')
            ->expectsQuestion('Repetí la contraseña', 'claveDeRescate')
            ->assertSuccessful();

        $this->post(route('login'), [
            'email' => 'unico@ejemplo.edu.gt',
            'password' => 'claveDeRescate',
        ])->assertRedirect(route('admin.panel'));
    }
}
