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

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAIMIENTO_SEGUNDOS);

            // Un solo mensaje para correo inexistente y contraseña equivocada: distinguirlos
            // convertiría el formulario en un verificador de qué cuentas existen.
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con ningún usuario del panel.',
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
