<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'correo' => $this->email,
            'rol' => $this->rol->value,
            'rol_nombre' => $this->rol->label(),
            'activo' => $this->activo,

            // Para que el panel sepa qué botones no ofrecer: no tiene sentido mostrar
            // «desactivar» sobre la única cuenta que puede administrar el sistema, ni sobre
            // la de quien está mirando la pantalla.
            'es_el_ultimo_administrador' => $this->isLastActiveAdministrator(),
            'es_uno_mismo' => $this->is($request->user()),

            'creado_en' => $this->created_at,
        ];
    }
}
