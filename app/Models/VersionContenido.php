<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única con el número de versión del conjunto de datos publicable.
 *
 * Es el contrato entre este backend y el proceso de compilación de Astro: si el número no
 * cambió, no hay nada que recompilar. El Service Worker de los clientes acaba viendo ese
 * mismo número y por él decide renovar su caché.
 *
 * @property int $numero
 */
#[Table('versiones_contenido')]
#[Fillable(['numero'])]
class VersionContenido extends Model
{
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
        ];
    }

    public static function numeroActual(): int
    {
        return static::registro()->numero;
    }

    /**
     * `increment()` emite `UPDATE ... SET numero = numero + 1`, que el motor resuelve
     * atómicamente. Dos guardados simultáneos suman dos, no uno.
     */
    public static function incrementar(): void
    {
        static::registro()->increment('numero');
    }

    private static function registro(): self
    {
        // `orderBy('id')` para que sea determinista: si alguna vez llegaran a existir dos
        // filas, siempre se lee y se incrementa la misma.
        return static::query()->orderBy('id')->first()
            ?? static::query()->create(['numero' => 1]);
    }
}
