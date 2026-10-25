<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumento;
use App\Observers\PublishableContentObserver;
use App\Observers\RevisionAuthorshipObserver;
use App\Policies\DocumentoPolicy;
use App\Support\Contenido\EnlaceIncrustable;
use App\Support\Mam\TextNormalizer;
use Database\Factories\DocumentoFactory;
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
 * Material descargable: los metadatos de un PDF que no vive aquí.
 *
 * `archivo_url` apunta a un archivo del frontend o de un CDN; este backend no aloja PDF. Se
 * descargan bajo demanda y el sitio no los precachea, para no gastar el plan de datos de
 * quien tiene 3G.
 *
 * Título y descripción pasan por el normalizador del Mam, como en `Pagina`. `licencia` no:
 * es una cita, igual que los datos de `Fuente`.
 *
 * @property TipoDocumento $tipo
 * @property bool $revisado
 */
#[Table('documentos')]
#[UsePolicy(DocumentoPolicy::class)]
#[ObservedBy([PublishableContentObserver::class, RevisionAuthorshipObserver::class])]
#[Fillable([
    'slug',
    'titulo',
    'descripcion',
    'tipo',
    'archivo_url',
    'tamano_kb',
    'licencia',
    'video_url',
    'fuente_id',
    'orden',
])]
class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory;

    protected $attributes = [
        'revisado' => false,
        'orden' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'tamano_kb' => 'integer',
            'orden' => 'integer',
            'revisado' => 'boolean',
            'revisado_en' => 'datetime',
        ];
    }

    protected function titulo(): Attribute
    {
        return Attribute::set(fn (?string $value): string => TextNormalizer::normalize((string) $value));
    }

    protected function descripcion(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => blank($value) ? null : TextNormalizer::normalize($value)
        );
    }

    /** Ver `Pagina::videoUrl()`. */
    protected function videoUrl(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => blank($value) ? null : EnlaceIncrustable::extraer($value)
        );
    }

    #[Scope]
    protected function enOrden(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('id');
    }

    #[Scope]
    protected function revisados(Builder $query): void
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
