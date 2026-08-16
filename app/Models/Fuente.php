<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ContentVersionObserver;
use App\Policies\FuentePolicy;
use Database\Factories\FuenteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trazabilidad bibliográfica: el libro y la edición de los que salió una entrada.
 *
 * Sin normalizador, a diferencia de `Entrada` y `Categoria`. Varios títulos del corpus están
 * en Mam («Tu'jil Qyol Mam te Tkab'in Yol»), pero esto es una **cita**: tiene que reproducir
 * la portada tal como está impresa. Nadie busca contra este campo y cambiarle los apóstrofos
 * al citarlo sería peor que conservarlos como venían. La regla de normalización obligatoria
 * es para los campos en idioma Mam del contenido, no para los metadatos de la fuente.
 */
#[Table('fuentes')]
#[UsePolicy(FuentePolicy::class)]
#[ObservedBy(ContentVersionObserver::class)]
#[Fillable(['titulo', 'institucion', 'anio', 'licencia', 'url'])]
class Fuente extends Model
{
    /** @use HasFactory<FuenteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
        ];
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class);
    }
}
