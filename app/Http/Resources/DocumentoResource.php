<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MuestraAutoria;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Salida del **panel**; la de la exportación la arma `ExportController`.
 *
 * @mixin Documento
 */
final class DocumentoResource extends JsonResource
{
    use MuestraAutoria;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo->value,
            'tipo_nombre' => $this->tipo->label(),
            'archivo_url' => $this->archivo_url,
            'tamano_kb' => $this->tamano_kb,
            'licencia' => $this->licencia,
            'video_url' => $this->video_url,
            'orden' => $this->orden,
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
