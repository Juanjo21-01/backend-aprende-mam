<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `php artisan mam:crear-usuario`.
 *
 * No hay registro público —el sitio de estudiantes no tiene cuentas, porque sus usuarios
 * son menores de edad—, así que este comando es la única puerta por la que entra un usuario
 * al sistema, y lo que el docente responsable necesita para dar de alta al validador
 * lingüístico sin depender del desarrollador.
 */
final class CrearUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_una_cuenta_con_su_rol(): void
    {
        $this->artisan('mam:crear-usuario')
            ->expectsQuestion('Nombre', 'María López')
            ->expectsQuestion('Correo electrónico', 'maria@ejemplo.edu.gt')
            ->expectsQuestion('Rol', UserRole::Administrator->value)
            ->expectsQuestion('Contraseña', 'unaClaveLarga')
            ->expectsQuestion('Repetí la contraseña', 'unaClaveLarga')
            ->assertSuccessful();

        $usuario = User::query()->where('email', 'maria@ejemplo.edu.gt')->firstOrFail();

        $this->assertSame('María López', $usuario->name);
        $this->assertSame(UserRole::Administrator, $usuario->rol);
        $this->assertTrue(Hash::check('unaClaveLarga', $usuario->password));
    }

    /** El rol de menor privilegio por defecto. */
    public function test_crea_un_editor_desde_las_opciones(): void
    {
        $this->artisan('mam:crear-usuario', [
            '--nombre' => 'Ana Pérez',
            '--correo' => 'ana@ejemplo.edu.gt',
            '--rol' => UserRole::Editor->value,
        ])
            ->expectsQuestion('Contraseña', 'otraClaveLarga')
            ->expectsQuestion('Repetí la contraseña', 'otraClaveLarga')
            ->assertSuccessful();

        $this->assertSame(
            UserRole::Editor,
            User::query()->where('email', 'ana@ejemplo.edu.gt')->firstOrFail()->rol
        );
    }

    public function test_no_admite_dos_cuentas_con_el_mismo_correo(): void
    {
        User::factory()->create(['email' => 'repetido@ejemplo.edu.gt']);

        $this->artisan('mam:crear-usuario', [
            '--nombre' => 'Otra Persona',
            '--correo' => 'repetido@ejemplo.edu.gt',
            '--rol' => UserRole::Editor->value,
        ])
            ->expectsQuestion('Contraseña', 'unaClaveLarga')
            ->expectsQuestion('Repetí la contraseña', 'unaClaveLarga')
            ->expectsOutputToContain('Ya existe una cuenta con ese correo.')
            ->assertFailed();

        $this->assertSame(1, User::query()->count());
    }

    public function test_exige_que_las_dos_contrasenas_coincidan(): void
    {
        $this->artisan('mam:crear-usuario', [
            '--nombre' => 'Ana Pérez',
            '--correo' => 'ana@ejemplo.edu.gt',
            '--rol' => UserRole::Editor->value,
        ])
            ->expectsQuestion('Contraseña', 'unaClaveLarga')
            ->expectsQuestion('Repetí la contraseña', 'otraDistinta')
            ->expectsOutputToContain('Las dos contraseñas no coinciden.')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_rechaza_un_rol_inventado(): void
    {
        $this->artisan('mam:crear-usuario', [
            '--nombre' => 'Ana Pérez',
            '--correo' => 'ana@ejemplo.edu.gt',
            '--rol' => 'superadministrador',
        ])
            ->expectsQuestion('Contraseña', 'unaClaveLarga')
            ->expectsQuestion('Repetí la contraseña', 'unaClaveLarga')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
