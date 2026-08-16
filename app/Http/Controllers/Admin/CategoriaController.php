<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoriaRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * CRUD de temas del vocabulario.
 */
final class CategoriaController extends Controller
{
    #[Authorize('viewAny', Categoria::class)]
    public function index(): AnonymousResourceCollection
    {
        // Sin paginar: son decenas de temas, no miles, y el formulario de entradas necesita
        // la lista completa para pintar el selector.
        $categorias = Categoria::query()
            ->withCount('entradas')
            ->enOrdenDelPanel()
            ->get();

        return CategoriaResource::collection($categorias);
    }

    #[Authorize('create', Categoria::class)]
    public function store(CategoriaRequest $request): CategoriaResource
    {
        return CategoriaResource::make(Categoria::create($request->validated()));
    }

    #[Authorize('view', 'categoria')]
    public function show(Categoria $categoria): CategoriaResource
    {
        return CategoriaResource::make($categoria->loadCount('entradas')->load('hijas'));
    }

    #[Authorize('update', 'categoria')]
    public function update(CategoriaRequest $request, Categoria $categoria): CategoriaResource
    {
        $categoria->update($request->validated());

        return CategoriaResource::make($categoria->loadCount('entradas'));
    }

    /**
     * Borrar un tema no toca las entradas: el pivote cae por cascada y los temas hijos
     * quedan huérfanos con `padre_id` nulo, no borrados. Perder la clasificación de una
     * palabra es reversible; perder la palabra, no.
     */
    #[Authorize('delete', 'categoria')]
    public function destroy(Categoria $categoria): Response
    {
        $categoria->delete();

        return response()->noContent();
    }
}
