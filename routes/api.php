<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CategoriaController;
use App\Http\Controllers\Admin\CategoriaGramaticalController;
use App\Http\Controllers\Admin\CurrentUserController;
use App\Http\Controllers\Admin\EntradaController;
use App\Http\Controllers\Admin\FuenteController;
use App\Http\Controllers\Admin\RevisionEntradaController;
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

    Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('yo', CurrentUserController::class)->name('yo');

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
    });

    Route::middleware('token.exportacion')->prefix('export')->name('export.')->group(function (): void {
        Route::get('vocabulario.json', [ExportController::class, 'vocabulario'])->name('vocabulario');
        Route::get('version', [ExportController::class, 'version'])->name('version');
    });
});
