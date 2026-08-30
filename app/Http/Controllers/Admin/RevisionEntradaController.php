<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntradaResource;
use App\Models\Entrada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
            $entrada->load(['categoriaGramatical', 'fuente', 'categorias', 'creador', 'revisor'])
        );
    }

    /**
     * Lo mismo, sobre una tanda.
     *
     * Existe por una cuenta concreta: el corpus del COLIMAM son 6,185 entradas, y aprobarlas
     * de a un clic es media jornada de clics. El validador filtra por fuente o por tema,
     * revisa lo que ve, y firma la página entera de una vez.
     *
     * Tres decisiones:
     *
     * - **Se autoriza entrada por entrada**, y no una sola vez sobre la clase. Hoy `revisar`
     *   solo mira el rol, pero su firma es por entrada; el día que dependa de la entrada,
     *   esto sigue siendo correcto en lugar de volverse un agujero.
     * - **Un tope de 200**, el mismo que el máximo de `por_pagina`: se firma lo que se está
     *   viendo. Sin tope, un cliente podría mandar las seis mil en una transacción.
     * - **Las que ya estaban como se piden no se tocan.** Eloquent no emite `UPDATE` si nada
     *   cambió, así que el observador no corre: marcar cincuenta de las que cuarenta ya
     *   estaban revisadas sube la versión diez veces, no cincuenta. Las que sí cambian dejan
     *   cada una su trabajo de publicación con retardo, igual que cuarenta guardados
     *   sueltos; el debounce hace que solo el último publique.
     */
    public function updateMany(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:entradas,id'],
            'revisado' => ['required', 'boolean'],
        ], [
            'ids.required' => 'No se indicó ninguna entrada.',
            'ids.array' => 'Las entradas deben venir como lista.',
            'ids.min' => 'No se indicó ninguna entrada.',
            'ids.max' => 'Son demasiadas entradas de una vez: el máximo es 200.',
            'ids.*.integer' => 'Hay un identificador de entrada que no es un número.',
            'ids.*.exists' => 'Alguna de las entradas ya no existe.',
            'revisado.required' => 'Falta indicar si las entradas quedan revisadas o no.',
            'revisado.boolean' => 'La marca de revisión debe ser sí o no.',
        ]);

        $entradas = Entrada::query()->whereKey($datos['ids'])->get();

        foreach ($entradas as $entrada) {
            Gate::authorize('revisar', $entrada);
        }

        // En una transacción: media tanda firmada es peor que ninguna, porque el validador
        // se queda sin saber dónde se cortó.
        $cambiadas = DB::transaction(function () use ($entradas, $datos): int {
            $cuenta = 0;

            foreach ($entradas as $entrada) {
                if ($entrada->revisado === $datos['revisado']) {
                    continue;
                }

                $entrada->revisado = $datos['revisado'];
                $entrada->save();
                $cuenta++;
            }

            return $cuenta;
        });

        return response()->json([
            'recibidas' => $entradas->count(),
            'actualizadas' => $cambiadas,
        ]);
    }
}
