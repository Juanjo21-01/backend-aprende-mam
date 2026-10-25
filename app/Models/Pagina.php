<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeccionPagina;
use App\Observers\PublishableContentObserver;
use App\Observers\RevisionAuthorshipObserver;
use App\Policies\PaginaPolicy;
use App\Support\Contenido\EnlaceIncrustable;
use App\Support\Mam\TextNormalizer;
use Database\Factories\PaginaFactory;
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

/**
 * Una página editorial del sitio: cultura, gramática o base legal.
 *
 * Título y cuerpo pasan por el normalizador del Mam en su mutator, igual que `Entrada::mam`
 * y por el mismo motivo: mezclan castellano con palabras y frases en Mam, y un apóstrofo
 * recto o una `õ` corrupta no se notan en pantalla. Están en el modelo y no en el
 * FormRequest para que también cubran seeders, tinker y cualquier importación futura.
 *
 * Sin columnas derivadas: las páginas no se buscan ni se ordenan por su texto. El orden lo
 * decide el docente con `orden`, nunca el alfabeto — `ORDER BY titulo` sería justo el error
 * que CLAUDE.md prohíbe si el título está en Mam.
 *
 * `revisado` no es asignable en masa: nace en falso y solo el validador lingüístico lo mueve.
 *
 * @property SeccionPagina $seccion
 * @property bool $revisado
 */
#[Table('paginas')]
#[UsePolicy(PaginaPolicy::class)]
#[ObservedBy([PublishableContentObserver::class, RevisionAuthorshipObserver::class])]
#[Fillable(['seccion', 'slug', 'titulo', 'cuerpo', 'orden', 'video_url', 'fuente_id'])]
class Pagina extends Model
{
    /** @use HasFactory<PaginaFactory> */
    use HasFactory;

    /** Ver `Entrada::$attributes`: el invariante queda escrito donde se lee. */
    protected $attributes = [
        'revisado' => false,
        'orden' => 0,
    ];

    protected function casts(): array
    {
        return [
            'seccion' => SeccionPagina::class,
            'orden' => 'integer',
            'revisado' => 'boolean',
            'revisado_en' => 'datetime',
        ];
    }

    protected function titulo(): Attribute
    {
        return Attribute::set(fn (?string $value): string => TextNormalizer::normalize((string) $value));
    }

    protected function cuerpo(): Attribute
    {
        return Attribute::set(fn (?string $value): string => TextNormalizer::normalize((string) $value));
    }

    /**
     * Se guarda siempre una URL: si el docente pegó el código `<iframe>` del proveedor, se
     * conserva su `src` y se descarta el HTML. Vacío es `null`, no cadena vacía.
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => blank($value) ? null : EnlaceIncrustable::extraer($value)
        );
    }

    /** El orden de lectura que fijó el docente; el `id` desempata para que sea estable. */
    #[Scope]
    protected function enOrden(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('id');
    }

    #[Scope]
    protected function revisadas(Builder $query): void
    {
        $query->where('revisado', true);
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(Fuente::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
