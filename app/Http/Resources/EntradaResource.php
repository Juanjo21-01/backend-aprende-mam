<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Entrada;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Entrada
 */
final class EntradaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mam' => $this->mam,
            'espanol' => $this->espanol,
            'definicion' => $this->definicion,

            // De solo lectura, pero a la vista: el Manual de Normas le pide al editor que
            // reporte un orden que le parezca equivocado, y sin ver la clave no tiene con
            // qué. También deja comprobar de un vistazo que la normalización hizo su parte.
            'busqueda' => $this->busqueda,
            'orden_alfabetico' => $this->orden_alfabetico,

            'municipio' => $this->municipio,
            'pagina_fuente' => $this->pagina_fuente,
            'revisado' => $this->revisado,
            'revisado_en' => $this->revisado_en,

            // Autoría en forma reducida, no con `UserResource`: eso traería correo, rol y
            // estado de cada cuenta en cada fila de un listado de cincuenta, y además
            // dispararía una consulta por fila para calcular si es el último administrador.
            'creado_por' => $this->usuario('creador'),
            'revisado_por' => $this->usuario('revisor'),

            'categoria_gramatical' => CategoriaGramaticalResource::make(
                $this->whenLoaded('categoriaGramatical')
            ),
            'fuente' => FuenteResource::make($this->whenLoaded('fuente')),
            'categorias' => CategoriaResource::collection($this->whenLoaded('categorias')),

            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
        ];
    }

    /**
     * La relación puede estar cargada y valer `null`: una entrada importada no tiene quien
     * la cargara, y una sin revisar no tiene revisor. Si no se cargó, la clave desaparece
     * de la respuesta en lugar de mentir con un `null`.
     */
    private function usuario(string $relacion): mixed
    {
        return $this->whenLoaded($relacion, function () use ($relacion): ?array {
            $usuario = $this->{$relacion};

            return $usuario === null ? null : [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
            ];
        });
    }
}
