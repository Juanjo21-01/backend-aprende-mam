<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaGramaticalResource;
use App\Models\CategoriaGramatical;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Solo lectura: alimenta el selector de clase de palabra del formulario de entradas.
 *
 * No tiene CRUD y no es un olvido. Es un catálogo normativo de ALMG, no contenido del
 * proyecto: se siembra con `CategoriasGramaticalesSeeder` y se cambia re-sembrando, igual
 * que el alfabeto. Dejar que el panel lo edite abriría la puerta a que alguien «arreglara»
 * las clases mayas mapeándolas a las del castellano.
 */
final class CategoriaGramaticalController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoriaGramaticalResource::collection(
            CategoriaGramatical::query()->orderBy('nombre')->get()
        );
    }
}
