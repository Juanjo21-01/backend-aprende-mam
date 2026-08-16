<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Personal del panel. No hay cuentas de estudiante ni registro público: los usuarios los
 * crea un administrador, desde el panel o con `php artisan mam:crear-usuario`.
 *
 * `rol` y `activo` quedan fuera de `#[Fillable]` a propósito. Son las dos columnas que
 * deciden qué puede hacer alguien, y dejarlas fuera de la asignación masiva garantiza que
 * nadie se ascienda a administrador colando un campo en el formulario de otra cosa. Se
 * asignan explícitamente, en el único sitio que corresponde y detrás de su política.
 *
 * @property UserRole $rol
 * @property bool $activo
 */
#[UsePolicy(UserPolicy::class)]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'activo' => true,
    ];

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
            'activo' => 'boolean',
        ];
    }

    /** El administrador es también el validador lingüístico: es quien marca lo revisado. */
    public function isAdministrator(): bool
    {
        return $this->rol === UserRole::Administrator;
    }

    /**
     * ¿Es el único administrador activo que queda en pie?
     *
     * La comprobación que impide dejar el sistema sin nadie que pueda administrarlo. Sin
     * ella basta un descuido —bajarse a editor, desactivar la cuenta equivocada— para que
     * el panel quede sin quien cree usuarios, borre contenido ni apruebe una revisión, y la
     * única salida sea la consola del servidor.
     */
    public function isLastActiveAdministrator(): bool
    {
        if (! $this->isAdministrator() || ! $this->activo) {
            return false;
        }

        return ! static::query()
            ->where('rol', UserRole::Administrator)
            ->where('activo', true)
            ->whereKeyNot($this->getKey())
            ->exists();
    }
}
