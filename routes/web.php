<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel de administración
|--------------------------------------------------------------------------
|
| Este dominio no sirve contenido público. El sitio de estudiantes y docentes
| se compila con Astro y se sirve como archivos estáticos desde un CDN; lo
| único que vive aquí es el panel y el endpoint de exportación que consume el
| proceso de compilación.
|
| Por eso `/` no muestra nada: manda al panel y ya.
|
*/

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [SessionController::class, 'create'])->name('login');
    Route::post('/admin/login', [SessionController::class, 'store']);
});

Route::middleware(['auth', 'usuario.activo'])->group(function (): void {
    Route::post('/admin/logout', [SessionController::class, 'destroy'])->name('logout');

    // Cascarón del panel. Atrapa las rutas hijas para que el enrutador de React pueda
    // manejarlas del lado del cliente sin que una recarga devuelva 404.
    Route::view('/admin/{ruta?}', 'admin.panel')
        ->where('ruta', '.*')
        ->name('admin.panel');
});
