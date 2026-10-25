<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

/**
 * Autoría en forma reducida (`{id, nombre}`) para las Resources del contenido que se revisa.
 *
 * No se usa `UserResource`: eso traería correo, rol y estado de cada cuenta en cada fila de
 * un listado, y además dispararía una consulta por fila para calcular si es el último
 * administrador.
 */
trait MuestraAutoria
{
    /**
     * La relación puede estar cargada y valer `null`: algo importado no tiene quien lo
     * cargara, y algo sin revisar no tiene revisor. Si no se cargó, la clave desaparece de
     * la respuesta en lugar de mentir con un `null`.
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
