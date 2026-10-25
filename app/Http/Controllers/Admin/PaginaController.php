<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SeccionPagina;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaginaRequest;
use App\Http\Resources\PaginaResource;
use App\Models\Pagina;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * CRUD de páginas editoriales: cultura, gramática y base legal.
 *
 * Misma política que las entradas: ambos roles cargan y corrigen, solo el administrador
 * borra. La marca de revisión tiene su propio controlador.
 */
final class PaginaController extends Controller
{
    private const RELACIONES = ['fuente', 'creador', 'revisor'];

    /**
     * Sin paginar: son decenas de páginas, no seis mil entradas, y el panel las agrupa por
     * sección en el cliente. `?seccion=` y `?revisado=` acotan, por si el listado crece.
     */
    #[Authorize('viewAny', Pagina::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $paginas = Pagina::query()
            ->with(self::RELACIONES)
            ->when(
                SeccionPagina::tryFrom((string) $request->query('seccion')) !== null,
                fn ($query) => $query->where('seccion', (string) $request->query('seccion'))
            )
            ->when(
                $request->has('revisado'),
                fn ($query) => $query->where('revisado', $request->boolean('revisado'))
            )
            ->orderBy('seccion')
            ->enOrden()
            ->get();

        return PaginaResource::collection($paginas);
    }

    #[Authorize('create', Pagina::class)]
    public function store(PaginaRequest $request): PaginaResource
    {
        return PaginaResource::make(Pagina::create($request->validated())->load(self::RELACIONES));
    }

    #[Authorize('view', 'pagina')]
    public function show(Pagina $pagina): PaginaResource
    {
        return PaginaResource::make($pagina->load(self::RELACIONES));
    }

    #[Authorize('update', 'pagina')]
    public function update(PaginaRequest $request, Pagina $pagina): PaginaResource
    {
        $pagina->update($request->validated());

        return PaginaResource::make($pagina->load(self::RELACIONES));
    }

    #[Authorize('delete', 'pagina')]
    public function destroy(Pagina $pagina): Response
    {
        $pagina->delete();

        return response()->noContent();
    }
}
