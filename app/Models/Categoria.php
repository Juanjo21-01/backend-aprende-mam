<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\PublishableContentObserver;
use App\Policies\CategoriaPolicy;
use App\Support\Mam\TextNormalizer;
use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Temas del vocabulario: animales, familia, colores, el mercado.
 *
 * `nombre_mam` es texto en idioma Mam y por tanto pasa por el normalizador, igual que
 * `entradas.mam`. No lleva columnas derivadas: los temas los ordena el docente por `orden`,
 * no el alfabeto, porque son la puerta de entrada del sitio y su secuencia es pedagógica.
 */
#[Table('categorias')]
#[UsePolicy(CategoriaPolicy::class)]
#[ObservedBy(PublishableContentObserver::class)]
#[Fillable(['nombre_es', 'nombre_mam', 'slug', 'icono', 'orden', 'padre_id'])]
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /**
     * La columna es anulable, así que un formulario que la manda vacía guarda `null` y no
     * una cadena vacía: dos formas de «no hay nombre en Mam» serían una de más.
     */
    protected function nombreMam(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => blank($value) ? null : TextNormalizer::normalize($value)
        );
    }

    /**
     * El orden lo decide el docente; dentro de un mismo peso, alfabético del castellano, y
     * el `id` desempata lo que aún quede igual. Sin ese último criterio, dos temas con el
     * mismo peso y el mismo nombre bailarían de sitio entre una carga y la siguiente.
     */
    #[Scope]
    protected function enOrdenDelPanel(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('nombre_es')->orderBy('id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    public function entradas(): BelongsToMany
    {
        return $this->belongsToMany(Entrada::class, 'entrada_categoria');
    }
}
