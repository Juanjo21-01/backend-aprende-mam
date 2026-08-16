<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Personal del panel. No hay cuentas de estudiante ni registro público: los usuarios los
 * crea el administrador con `php artisan mam:crear-usuario`.
 *
 * `rol` queda fuera de `#[Fillable]` a propósito. Hoy ningún endpoint construye usuarios a
 * partir de un request, y dejarlo fuera garantiza que el día que exista una pantalla de
 * gestión de usuarios nadie se ascienda a administrador colando un campo en el formulario.
 *
 * @property UserRole $rol
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => UserRole::class,
        ];
    }

    /** El administrador es también el validador lingüístico: es quien marca lo revisado. */
    public function isAdministrator(): bool
    {
        return $this->rol === UserRole::Administrator;
    }
}
