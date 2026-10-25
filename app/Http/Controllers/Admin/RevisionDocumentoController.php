<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentoResource;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Marca o desmarca un documento como revisado. Ver `RevisionPaginaController`.
 */
final class RevisionDocumentoController extends Controller
{
    #[Authorize('revisar', 'documento')]
    public function update(Request $request, Documento $documento): DocumentoResource
    {
        $datos = $request->validate([
            'revisado' => ['required', 'boolean'],
        ], [
            'revisado.required' => 'Falta indicar si el documento queda revisado o no.',
            'revisado.boolean' => 'La marca de revisión debe ser sí o no.',
        ]);

        $documento->revisado = $datos['revisado'];
        $documento->save();

        return DocumentoResource::make($documento->load(['fuente', 'creador', 'revisor']));
    }
}
