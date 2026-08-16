<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CategoriaGramatical;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CategoriaGramatical
 */
final class CategoriaGramaticalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'abreviatura' => $this->abreviatura,
            'nombre' => $this->nombre,

            // Para las cuatro clases mayas sin equivalente en castellano, es lo que le dice
            // al editor qué está eligiendo.
            'descripcion' => $this->descripcion,
        ];
    }
}
