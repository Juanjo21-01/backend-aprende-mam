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
 * @property int|null $publicado_numero
 * @property \Illuminate\Support\Carbon|null $publicado_en
 */
#[Table('versiones_contenido')]
#[Fillable(['numero'])]
class VersionContenido extends Model
{
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'publicado_numero' => 'integer',
            'publicado_en' => 'datetime',
        ];
    }

    public static function numeroActual(): int
    {
        return static::actual()->numero;
    }

    /**
     * La fila entera, para quien necesita el número y lo publicado a la vez.
     *
     * El panel la usa para responder «¿lo que guardé ya salió?», que es comparar dos
     * columnas de esta misma fila y nada más.
     */
    public static function actual(): self
    {
        return static::registro();
    }

    /**
     * Deja constancia de qué versión aceptó compilar el proveedor.
     *
     * Se llama **después** de que el hook respondió bien, nunca antes: anotarlo al
     * despachar diría que está publicado algo que quizá falló, y el docente vería «al día»
     * mientras el sitio sigue con contenido viejo.
     *
     * `publicado_numero` puede quedar por detrás de `numero` —si alguien guardó mientras
     * compilaba— y también puede igualarlo. Lo que no debe es retroceder, así que un aviso
     * tardío de una versión vieja no pisa a uno más nuevo.
     */
    public static function marcarPublicada(int $numero): void
    {
        $registro = static::registro();

        if ($registro->publicado_numero !== null && $registro->publicado_numero > $numero) {
            return;
        }

        $registro->publicado_numero = $numero;
        $registro->publicado_en = now();
        $registro->save();
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
