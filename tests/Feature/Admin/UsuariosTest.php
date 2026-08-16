<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Gestión de cuentas del panel.
 *
 * Lo que más se prueba acá no es el CRUD sino las guardas: las tres formas de quedarse
 * fuera del propio sistema para siempre, que son bajarse el rol, desactivarse a uno mismo
 * y dejar al panel sin ningún administrador activo.
 */
final class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        return User::factory()->administrador()->create();
    }

    public function test_el_editor_no_ve_las_cuentas(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->getJson(route('admin.usuarios.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede gestionar las cuentas del panel.');
    }

    public function test_el_administrador_lista_las_cuentas(): void
    {
        $admin = $this->administrador();
        User::factory()->editor()->create();

        $this->actingAs($admin)
            ->getJson(route('admin.usuarios.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_el_administrador_crea_una_cuenta(): void
    {
        $this->actingAs($this->administrador())
            ->postJson(route('admin.usuarios.store'), [
                'name' => 'Ana Pérez',
                'email' => 'ana@ejemplo.edu.gt',
                'rol' => UserRole::Editor->value,
                'password' => 'unaClaveLarga',
                'password_confirmation' => 'unaClaveLarga',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rol', 'editor')
            ->assertJsonPath('data.activo', true);

        $usuario = User::query()->where('email', 'ana@ejemplo.edu.gt')->firstOrFail();
        $this->assertTrue(Hash::check('unaClaveLarga', $usuario->password));
    }

    public function test_no_admite_dos_cuentas_con_el_mismo_correo(): void
    {
        User::factory()->create(['email' => 'repetido@ejemplo.edu.gt']);

        $this->actingAs($this->administrador())
            ->postJson(route('admin.usuarios.store'), [
                'name' => 'Otra Persona',
                'email' => 'repetido@ejemplo.edu.gt',
                'rol' => UserRole::Editor->value,
                'password' => 'unaClaveLarga',
                'password_confirmation' => 'unaClaveLarga',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    /** La contraseña tiene su propia acción, que además cierra las sesiones abiertas. */
    public function test_la_edicion_no_acepta_contrasena(): void
    {
        $usuario = User::factory()->editor()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.usuarios.update', $usuario), [
                'password' => 'otraClaveLarga',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_el_administrador_cambia_el_rol_de_otro(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.usuarios.update', $editor), [
                'rol' => UserRole::Administrator->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.rol', 'administrador');
    }

    // --- Las guardas que impiden quedarse fuera del panel ---------------------------

    /**
     * La más importante de todas: si el último administrador se baja a editor, el panel
     * queda sin nadie que pueda crear cuentas, borrar contenido ni aprobar una revisión, y
     * la única salida es la consola del servidor.
     */
    public function test_el_ultimo_administrador_no_puede_bajarse_el_rol(): void
    {
        $admin = $this->administrador();
        User::factory()->editor()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.usuarios.update', $admin), ['rol' => UserRole::Editor->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rol');

        $this->assertTrue($admin->fresh()->isAdministrator());
    }

    /** Ni siquiera con otro administrador en pie: cerrar la puerta desde adentro. */
    public function test_nadie_puede_cambiarse_el_rol_a_si_mismo(): void
    {
        $admin = $this->administrador();
        User::factory()->administrador()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.usuarios.update', $admin), ['rol' => UserRole::Editor->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rol');
    }

    public function test_se_puede_bajar_el_rol_si_queda_otro_administrador(): void
    {
        $admin = $this->administrador();
        $otro = User::factory()->administrador()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.usuarios.update', $otro), ['rol' => UserRole::Editor->value])
            ->assertOk();

        $this->assertFalse($otro->fresh()->isAdministrator());
    }

    public function test_nadie_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->administrador();
        User::factory()->administrador()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.usuarios.estado', $admin), ['activo' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('activo');

        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_el_ultimo_administrador_activo_no_se_puede_desactivar(): void
    {
        $admin = $this->administrador();
        $otro = User::factory()->administrador()->desactivado()->create();

        // El otro administrador existe pero está desactivado, así que `$admin` sigue
        // siendo el único en pie.
        $this->actingAs($admin)
            ->patchJson(route('admin.usuarios.estado', $admin), ['activo' => false])
            ->assertUnprocessable();

        $this->assertTrue($admin->fresh()->activo);
        $this->assertFalse($otro->fresh()->activo);
    }

    // --- Desactivación --------------------------------------------------------------

    public function test_el_administrador_desactiva_una_cuenta(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.usuarios.estado', $editor), ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertFalse($editor->fresh()->activo);
    }

    /** La baja es reversible; ese es el motivo de no borrar cuentas. */
    public function test_una_cuenta_desactivada_se_vuelve_a_habilitar(): void
    {
        $editor = User::factory()->editor()->desactivado()->create();

        $this->actingAs($this->administrador())
            ->patchJson(route('admin.usuarios.estado', $editor), ['activo' => true])
            ->assertOk();

        $this->assertTrue($editor->fresh()->activo);
    }

    /**
     * No hay borrado de cuentas en el panel: la baja es la bandera `activo`.
     *
     * Responde 405 y no 404 porque la dirección sí existe —se usa para consultar y editar—;
     * lo que no existe es el verbo. Es la respuesta correcta y, de paso, deja claro que la
     * ruta se quitó a propósito y no que alguien escribió mal la URL.
     */
    public function test_no_existe_el_borrado_de_cuentas(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($this->administrador())
            ->deleteJson('/api/v1/admin/usuarios/'.$editor->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $editor->id]);
    }

    public function test_una_cuenta_desactivada_no_puede_entrar(): void
    {
        User::factory()->desactivado()->create(['email' => 'fuera@ejemplo.edu.gt']);

        $this->post(route('login'), [
            'email' => 'fuera@ejemplo.edu.gt',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Desactivar tiene que surtir efecto ahora, no cuando expire la sesión dentro de dos
     * horas. Sin esta comprobación, «desactivar» sería una sugerencia.
     */
    public function test_desactivar_corta_la_sesion_ya_abierta(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->getJson(route('admin.yo'))->assertOk();

        $editor->activo = false;
        $editor->save();

        $this->actingAs($editor)
            ->getJson(route('admin.yo'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta está desactivada. Pedile a un administrador que la habilite.');
    }

    public function test_el_panel_tambien_echa_a_una_cuenta_desactivada(): void
    {
        $editor = User::factory()->editor()->desactivado()->create();

        $this->withoutVite()
            ->actingAs($editor)
            ->get('/admin')
            ->assertForbidden();
    }
}
