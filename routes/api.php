<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CategoriaController;
use App\Http\Controllers\Admin\CategoriaGramaticalController;
use App\Http\Controllers\Admin\CurrentUserController;
use App\Http\Controllers\Admin\CurrentUserPasswordController;
use App\Http\Controllers\Admin\EntradaController;
use App\Http\Controllers\Admin\FuenteController;
use App\Http\Controllers\Admin\PublicacionController;
use App\Http\Controllers\Admin\RevisionEntradaController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Dos consumidores y ninguno más:
|
|   1. El panel de administración, que corre en este mismo origen y se
|      autentica con la cookie de sesión (`auth:sanctum` sobre `statefulApi`).
|   2. El proceso de compilación del sitio estático, que lee la exportación
|      con un token de `.env`.
|
| **No hay rutas públicas de consulta, y no es un olvido.** El sitio de
| estudiantes y docentes se sirve como archivos estáticos desde un CDN; una
| ruta pública aquí metería tráfico de escuelas al cPanel compartido y
| tumbaría el modelo de despliegue entero.
|
*/

Route::prefix('v1')->group(function (): void {

    Route::middleware(['auth:sanctum', 'usuario.activo'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('yo', CurrentUserController::class)->name('yo');

        // Sobre la cuenta propia y ninguna otra: no lleva identificador en la ruta.
        // La puede usar cualquiera de los dos roles, y pide la contraseña actual.
        Route::patch('yo/contrasena', [CurrentUserPasswordController::class, 'update'])
            ->name('yo.contrasena');

        // Cuentas del panel. Solo administradores; lo impone `UserPolicy`.
        // Sin `destroy`: la baja de una cuenta es desactivarla, no borrarla.
        Route::apiResource('usuarios', UserController::class)->except('destroy')
            ->parameters(['usuarios' => 'usuario']);

        Route::patch('usuarios/{usuario}/contrasena', [UserPasswordController::class, 'update'])
            ->name('usuarios.contrasena');

        Route::patch('usuarios/{usuario}/estado', [UserStatusController::class, 'update'])
            ->name('usuarios.estado');

        // **Antes del `apiResource`, y no da igual.** `entradas/{entrada}` casa también con
        // `entradas/revisiones`, y el enrutador se queda con la primera que coincida. Si
        // esta línea baja de sitio, el lote responde 404 buscando una entrada que se
        // llamara «revisiones».
        Route::patch('entradas/revisiones', [RevisionEntradaController::class, 'updateMany'])
            ->name('entradas.revisiones');

        Route::apiResource('entradas', EntradaController::class);

        // Fuera del recurso a propósito: no es una edición más, es la firma del validador
        // lingüístico y el interruptor de lo que se publica.
        Route::patch('entradas/{entrada}/revision', [RevisionEntradaController::class, 'update'])
            ->name('entradas.revision');

        Route::apiResource('categorias', CategoriaController::class);
        Route::apiResource('fuentes', FuenteController::class);

        // Catálogo normativo de ALMG: se lee, no se edita. Se cambia re-sembrando.
        Route::get('categorias-gramaticales', [CategoriaGramaticalController::class, 'index'])
            ->name('categorias-gramaticales.index');

        // Si lo guardado ya salió al sitio. De solo lectura y para los dos roles: quien
        // carga vocabulario tiene el mismo derecho a saberlo que quien lo administra.
        Route::get('publicacion', PublicacionController::class)->name('publicacion');
    });

    Route::middleware('token.exportacion')->prefix('export')->name('export.')->group(function (): void {
        Route::get('vocabulario.json', [ExportController::class, 'vocabulario'])->name('vocabulario');
        Route::get('version', [ExportController::class, 'version'])->name('version');
    });
});
