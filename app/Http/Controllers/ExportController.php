<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\VersionContenido;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

/**
 * La única salida de datos de este backend hacia el mundo, y es de solo lectura.
 *
 * El sitio público no consulta esta API en tiempo de ejecución: la lee **una vez**, durante
 * la compilación de Astro, y a partir de ahí sirve archivos estáticos desde un CDN. Ese es
 * el motivo de que el alojamiento compartido aguante el proyecto: el tráfico de estudiantes
 * nunca llega hasta aquí.
 *
 * Va protegido por el token estático de `.env` (`VerifyExportToken`), no por sesión: quien
 * llama es un proceso de compilación, no un navegador.
 */
final class ExportController extends Controller
{
    /**
     * Solo entra lo revisado.
     *
     * Es el requisito de rigor del proyecto: nada llega a manos de una estudiante sin que
     * el validador lingüístico haya dado fe de que el Mam está bien escrito. Mientras no se
     * revise el primer lote, el diccionario público sale vacío, y eso es lo correcto.
     */
    public function vocabulario(): StreamedJsonResponse
    {
        $entradas = Entrada::query()
            ->revisadas()
            ->with([
                'categoriaGramatical:id,abreviatura,nombre',
                'fuente:id,titulo,institucion,anio',
                'categorias:id,slug',
            ])
            ->enOrdenMam()
            // `lazy()` y no `get()`: seis mil entradas con sus relaciones no se materializan
            // en memoria de un cPanel compartido. El JSON sale por partes conforme se lee.
            ->lazy();

        return response()->streamJson([
            'version' => VersionContenido::numeroActual(),
            'generado_en' => now()->toIso8601String(),

            // Los temas van completos y no solo referenciados: cada entrada trae los suyos
            // por `slug`, y sin este catálogo esos slugs no tienen nombre con el que
            // pintar la navegación del sitio.
            'categorias' => $this->categorias(),

            'entradas' => $entradas->map($this->comoArreglo(...)),
        ]);
    }

    /**
     * Endpoint ligero de versión.
     *
     * Lo consulta la compilación antes de descargar el vocabulario entero: si el número no
     * cambió, no hay nada que hacer y se ahorra el trabajo.
     */
    public function version(): JsonResponse
    {
        return response()->json([
            'version' => VersionContenido::numeroActual(),
        ]);
    }

    /**
     * El catálogo de temas, con la jerarquía en la misma moneda que el resto.
     *
     * `padre` sale como **slug**, no como el `id` de la fila. Fuera de aquí el `id` de una
     * categoría no existe: las entradas traen sus temas por slug y el catálogo tampoco
     * exporta el suyo, así que un `padre` numérico no se puede resolver contra nada y la
     * jerarquía queda irreconstruible en el sitio. Da igual mientras todos los temas sean
     * de primer nivel —y por eso pasó desapercibido—, pero rompe en cuanto haya uno anidado.
     *
     * El slug del padre se busca en la colección ya cargada y no con la relación `padre`:
     * son decenas de temas y vienen todos, así que cargarla sería una consulta por fila
     * para releer algo que ya está en memoria.
     *
     * @return list<array<string, mixed>>
     */
    private function categorias(): array
    {
        $categorias = Categoria::query()
            ->enOrdenDelPanel()
            ->get(['id', 'nombre_es', 'nombre_mam', 'slug', 'icono', 'orden', 'padre_id']);

        $slugPorId = $categorias->pluck('slug', 'id');

        return $categorias
            ->map(fn (Categoria $categoria): array => [
                'slug' => $categoria->slug,
                'nombre_es' => $categoria->nombre_es,
                'nombre_mam' => $categoria->nombre_mam,
                'icono' => $categoria->icono,
                'orden' => $categoria->orden,
                'padre' => $categoria->padre_id === null
                    ? null
                    : $slugPorId->get($categoria->padre_id),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function comoArreglo(Entrada $entrada): array
    {
        return [
            'id' => $entrada->id,
            'mam' => $entrada->mam,
            'espanol' => $entrada->espanol,
            'definicion' => $entrada->definicion,

            // Las dos derivadas viajan calculadas. El buscador del cliente compara contra
            // `busqueda` y la navegación alfabética ordena por `orden`, así que el navegador
            // no tiene que saber nada de la ortografía del Mam: eso ya se resolvió aquí.
            'busqueda' => $entrada->busqueda,
            'orden' => $entrada->orden_alfabetico,

            'clase' => $entrada->categoriaGramatical?->abreviatura,
            'municipio' => $entrada->municipio,
            'temas' => $entrada->categorias->pluck('slug')->all(),

            // Cada entrada carga su procedencia: es el requisito académico de trazabilidad,
            // y así el sitio puede citarla al pie sin una segunda petición.
            'fuente' => $entrada->fuente === null ? null : [
                'titulo' => $entrada->fuente->titulo,
                'institucion' => $entrada->fuente->institucion,
                'anio' => $entrada->fuente->anio,
                'pagina' => $entrada->pagina_fuente,
            ],
        ];
    }
}
