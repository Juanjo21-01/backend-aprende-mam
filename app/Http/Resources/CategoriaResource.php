<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Categoria
 */
final class CategoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_es' => $this->nombre_es,
            'nombre_mam' => $this->nombre_mam,
            'slug' => $this->slug,
            'icono' => $this->icono,
            'orden' => $this->orden,
            'padre_id' => $this->padre_id,
            'hijas' => self::collection($this->whenLoaded('hijas')),
            'total_entradas' => $this->whenCounted('entradas'),
        ];
    }
}
