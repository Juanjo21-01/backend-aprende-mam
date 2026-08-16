<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoriaGramaticalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Clases de palabra de ALMG. Catálogo, no contenido: lo siembra
 * `CategoriasGramaticalesSeeder` y el panel solo lo lee para llenar el desplegable del
 * formulario de entradas.
 *
 * Cuatro de sus filas —posicional, afectivo, direccional y clasificador— son clases propias
 * de las lenguas mayas sin equivalente en castellano.
 */
#[Table('categorias_gramaticales')]
#[Fillable(['abreviatura', 'nombre', 'descripcion'])]
class CategoriaGramatical extends Model
{
    /** @use HasFactory<CategoriaGramaticalFactory> */
    use HasFactory;

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class);
    }
}
