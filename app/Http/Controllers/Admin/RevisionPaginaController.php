<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaginaResource;
use App\Models\Pagina;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Marca o desmarca una página como revisada.
 *
 * Endpoint aparte del CRUD por la misma razón que `RevisionEntradaController`: es la firma
 * del validador lingüístico y el interruptor de lo que se publica, y conviene que se
 * accione a propósito y no de refilón al guardar otra cosa. Se puede volver atrás.
 */
final class RevisionPaginaController extends Controller
{
    #[Authorize('revisar', 'pagina')]
    public function update(Request $request, Pagina $pagina): PaginaResource
    {
        $datos = $request->validate([
            'revisado' => ['required', 'boolean'],
        ], [
            'revisado.required' => 'Falta indicar si la página queda revisada o no.',
            'revisado.boolean' => 'La marca de revisión debe ser sí o no.',
        ]);

        $pagina->revisado = $datos['revisado'];
        $pagina->save();

        return PaginaResource::make($pagina->load(['fuente', 'creador', 'revisor']));
    }
}
