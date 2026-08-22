<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EntradaRequest;
use App\Http\Resources\EntradaResource;
use App\Models\Entrada;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de entradas del diccionario.
 *
 * La autorización va por atributo en cada acción, sobre `EntradaPolicy`: ambos roles cargan
 * y corrigen; solo el administrador borra. La marca de revisión tiene su propio controlador,
 * porque es una atribución distinta —la del validador lingüístico— y no una edición más.
 */
final class EntradaController extends Controller
{
    /** Relaciones que el panel siempre necesita para pintar una fila o un formulario. */
    private const RELACIONES = ['categoriaGramatical', 'fuente', 'categorias', 'creador', 'revisor'];

    #[Authorize('viewAny', Entrada::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $entradas = Entrada::query()
            ->with(self::RELACIONES)
            ->when(
                $request->filled('buscar'),
                // Contra `busqueda`, nunca contra `mam`: el scope pasa el término por la
                // misma función que generó la columna, así que da igual si el editor
                // teclea `qaq`, `q'aq'` o `Q’AQ’`.
                fn ($query) => $query->buscar((string) $request->string('buscar'))
            )
            ->when(
                $request->has('revisado'),
                fn ($query) => $query->where('revisado', $request->boolean('revisado'))
            )
            ->when(
                $request->filled('categoria'),
                fn ($query) => $query->whereHas(
                    'categorias',
                    fn ($tema) => $tema->whereKey($request->integer('categoria'))
                )
            )
            ->enOrdenMam()
            ->paginate(perPage: min($request->integer('por_pagina', 50), 200))
            ->withQueryString();

        return EntradaResource::collection($entradas);
    }

    #[Authorize('create', Entrada::class)]
    public function store(EntradaRequest $request): EntradaResource
    {
        // La entrada y sus temas son un solo hecho: una entrada guardada con la mitad de
        // sus temas es peor que una que no se guardó.
        $entrada = DB::transaction(function () use ($request): Entrada {
            $entrada = Entrada::create($request->safe()->except('categorias'));

            $entrada->categorias()->sync($request->input('categorias', []));

            return $entrada;
        });

        return EntradaResource::make($entrada->load(self::RELACIONES));
    }

    #[Authorize('view', 'entrada')]
    public function show(Entrada $entrada): EntradaResource
    {
        return EntradaResource::make($entrada->load(self::RELACIONES));
    }

    #[Authorize('update', 'entrada')]
    public function update(EntradaRequest $request, Entrada $entrada): EntradaResource
    {
        DB::transaction(function () use ($request, $entrada): void {
            $entrada->update($request->safe()->except('categorias'));

            // Solo si vinieron: una edición parcial que no menciona los temas no puede
            // dejar la entrada sin ninguno.
            if ($request->has('categorias')) {
                $entrada->categorias()->sync($request->input('categorias', []));
            }
        });

        return EntradaResource::make($entrada->load(self::RELACIONES));
    }

    #[Authorize('delete', 'entrada')]
    public function destroy(Entrada $entrada): Response
    {
        $entrada->delete();

        return response()->noContent();
    }
}
