<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MuestraAutoria;
use App\Models\Pagina;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Salida del **panel**; la de la exportación la arma `ExportController`.
 *
 * @mixin Pagina
 */
final class PaginaResource extends JsonResource
{
    use MuestraAutoria;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seccion' => $this->seccion->value,
            'seccion_nombre' => $this->seccion->label(),
            'slug' => $this->slug,
            'titulo' => $this->titulo,
            'cuerpo' => $this->cuerpo,
            'orden' => $this->orden,
            'video_url' => $this->video_url,
            'fuente_id' => $this->fuente_id,
            'fuente' => FuenteResource::make($this->whenLoaded('fuente')),

            'revisado' => $this->revisado,
            'revisado_en' => $this->revisado_en,
            'creado_por' => $this->usuario('creador'),
            'revisado_por' => $this->usuario('revisor'),

            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
        ];
    }
}
