<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Acceso al panel. Formulario Blade servido por el propio Laravel y sesión del grupo `web`.
 *
 * El panel corre en este mismo origen, así que el login no necesita JavaScript ni el baile
 * de la cookie de CSRF de Sanctum: un formulario normal, con su token, resuelve el caso.
 * El React del panel arranca ya autenticado y nunca toca credenciales.
 */
final class SessionController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Contra la fijación de sesión: el identificador con el que llegó el visitante
        // anónimo no puede ser el mismo con el que sale autenticado.
        $request->session()->regenerate();

        return redirect()->intended(route('admin.panel'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
