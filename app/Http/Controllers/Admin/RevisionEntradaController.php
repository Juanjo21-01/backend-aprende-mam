<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntradaResource;
use App\Models\Entrada;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Marca o desmarca una entrada como revisada.
 *
 * Endpoint aparte del CRUD, y no un campo más del formulario, porque es una atribución
 * distinta: el editor carga y corrige, pero solo el validador lingüístico da fe de que el
 * Mam está bien escrito. Es también el interruptor de publicación —la exportación solo
 * lleva lo revisado—, así que conviene que se accione a propósito y no de refilón al
 * guardar otra cosa.
 *
 * Es el único sitio donde `revisado` llega desde un request; `EntradaRequest` lo prohíbe.
 */
final class RevisionEntradaController extends Controller
{
    #[Authorize('revisar', 'entrada')]
    public function update(Request $request, Entrada $entrada): EntradaResource
    {
        $datos = $request->validate([
            'revisado' => ['required', 'boolean'],
        ], [
            'revisado.required' => 'Falta indicar si la entrada queda revisada o no.',
        ]);

        // Se permite volver atrás: si el validador detecta que aprobó algo por error, tiene
        // que poder sacarlo de la publicación sin borrar la entrada ni perder el trabajo.
        $entrada->revisado = $datos['revisado'];
        $entrada->save();

        return EntradaResource::make(
            $entrada->load(['categoriaGramatical', 'fuente', 'categorias'])
        );
    }
}
