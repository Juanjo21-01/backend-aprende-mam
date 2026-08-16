<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Acceso al panel, con el límite de intentos que pide la Especificación Técnica.
 *
 * El contador va por correo **más** dirección de origen: por correo solo, cualquiera
 * podría dejar fuera al docente probando su cuenta a propósito; por dirección sola, una
 * escuela entera detrás de una misma salida a internet se bloquearía entre sí.
 */
final class LoginRequest extends FormRequest
{
    /** Cinco intentos por minuto: de sobra para quien se equivoca, poco para quien prueba. */
    private const MAX_INTENTOS = 5;

    private const DECAIMIENTO_SEGUNDOS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // `activo` viaja como condición de la consulta, no como comprobación posterior: así
        // una cuenta desactivada ni siquiera llega a compararse la contraseña.
        $credenciales = $this->only('email', 'password') + ['activo' => true];

        // `Auth::guard('web')` y no `Auth::attempt()`: este último usa el guard *por
        // defecto*, que es estado global y puede haber quedado en `sanctum` —un guard de
        // petición, sin `attempt()`— si algo lo cambió antes. El acceso no puede depender
        // de eso. `SessionController::destroy()` ya nombraba su guard igual de explícito.
        if (! Auth::guard('web')->attempt($credenciales, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAIMIENTO_SEGUNDOS);

            // Un solo mensaje para las tres causas —correo inexistente, contraseña
            // equivocada y cuenta desactivada— porque distinguirlas convertiría el
            // formulario en un verificador de qué cuentas existen. A quien esté
            // desactivado se lo dice un administrador, que sí lo ve en el panel.
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con ningún usuario activo del panel.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_INTENTOS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Demasiados intentos fallidos. Volvé a probar en %d segundos.',
                RateLimiter::availableIn($this->throttleKey())
            ),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }
}
