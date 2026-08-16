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

            'categoria_gramatical' => CategoriaGramaticalResource::make(
                $this->whenLoaded('categoriaGramatical')
            ),
            'fuente' => FuenteResource::make($this->whenLoaded('fuente')),
            'categorias' => CategoriaResource::collection($this->whenLoaded('categorias')),

            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
        ];
    }
}
