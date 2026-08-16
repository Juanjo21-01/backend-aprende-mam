<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Fuente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Fuente
 */
final class FuenteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'institucion' => $this->institucion,
            'anio' => $this->anio,
            'licencia' => $this->licencia,
            'url' => $this->url,
            'total_entradas' => $this->whenCounted('entradas'),
        ];
    }
}
