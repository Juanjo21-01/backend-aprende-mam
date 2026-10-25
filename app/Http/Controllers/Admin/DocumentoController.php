<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentoRequest;
use App\Http\Resources\DocumentoResource;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * CRUD de documentos descargables. Solo metadatos: el PDF no pasa por aquí.
 */
final class DocumentoController extends Controller
{
    private const RELACIONES = ['fuente', 'creador', 'revisor'];

    #[Authorize('viewAny', Documento::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $documentos = Documento::query()
            ->with(self::RELACIONES)
            ->when(
                TipoDocumento::tryFrom((string) $request->query('tipo')) !== null,
                fn ($query) => $query->where('tipo', (string) $request->query('tipo'))
            )
            ->when(
                $request->has('revisado'),
                fn ($query) => $query->where('revisado', $request->boolean('revisado'))
            )
            ->enOrden()
            ->get();

        return DocumentoResource::collection($documentos);
    }

    #[Authorize('create', Documento::class)]
    public function store(DocumentoRequest $request): DocumentoResource
    {
        return DocumentoResource::make(Documento::create($request->validated())->load(self::RELACIONES));
    }

    #[Authorize('view', 'documento')]
    public function show(Documento $documento): DocumentoResource
    {
        return DocumentoResource::make($documento->load(self::RELACIONES));
    }

    #[Authorize('update', 'documento')]
    public function update(DocumentoRequest $request, Documento $documento): DocumentoResource
    {
        $documento->update($request->validated());

        return DocumentoResource::make($documento->load(self::RELACIONES));
    }

    #[Authorize('delete', 'documento')]
    public function destroy(Documento $documento): Response
    {
        $documento->delete();

        return response()->noContent();
    }
}
