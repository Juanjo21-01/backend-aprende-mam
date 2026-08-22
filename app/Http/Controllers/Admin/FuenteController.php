<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FuenteRequest;
use App\Http\Resources\FuenteResource;
use App\Models\Fuente;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * CRUD de fuentes bibliográficas.
 */
final class FuenteController extends Controller
{
    #[Authorize('viewAny', Fuente::class)]
    public function index(): AnonymousResourceCollection
    {
        $fuentes = Fuente::query()
            ->withCount('entradas')
            ->orderBy('titulo')
            ->orderBy('id') // Desempate: dos ediciones de la misma obra comparten título.
            ->get();

        return FuenteResource::collection($fuentes);
    }

    #[Authorize('create', Fuente::class)]
    public function store(FuenteRequest $request): FuenteResource
    {
        return FuenteResource::make(Fuente::create($request->validated()));
    }

    #[Authorize('view', 'fuente')]
    public function show(Fuente $fuente): FuenteResource
    {
        return FuenteResource::make($fuente->loadCount('entradas'));
    }

    #[Authorize('update', 'fuente')]
    public function update(FuenteRequest $request, Fuente $fuente): FuenteResource
    {
        $fuente->update($request->validated());

        return FuenteResource::make($fuente->loadCount('entradas'));
    }

    /**
     * Las entradas que la citaban quedan con `fuente_id` nulo, no se borran: la FK es
     * `nullOnDelete`. Se pierde la trazabilidad de esas palabras, que es grave, pero
     * perder el léxico lo sería mucho más.
     */
    #[Authorize('delete', 'fuente')]
    public function destroy(Fuente $fuente): Response
    {
        $fuente->delete();

        return response()->noContent();
    }
}
