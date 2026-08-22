<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\EntradaAuthorshipObserver;
use App\Observers\PublishableContentObserver;
use App\Policies\EntradaPolicy;
use App\Support\Mam\SortKeyGenerator;
use App\Support\Mam\TextNormalizer;
use Database\Factories\EntradaFactory;
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

/**
 * Entidad central del diccionario.
 *
 * Aquí es donde la ortografía del Mam deja de ser una biblioteca y se vuelve un dato
 * guardado. El mutator de `mam` es la única puerta: normaliza el lema y deriva `busqueda` y
 * `orden_alfabetico` en el mismo paso. Está en el modelo y no en un FormRequest a propósito,
 * porque también tienen que pasar por él los seeders, el importador del corpus y tinker.
 *
 * `revisado` no es asignable en masa: toda entrada nace sin revisar y solo el validador
 * lingüístico la mueve, a través de su propio endpoint y su propia política.
 *
 * @property string $mam
 * @property string $busqueda
 * @property string $orden_alfabetico
 * @property bool $revisado
 */
#[Table('entradas')]
#[UsePolicy(EntradaPolicy::class)]
#[ObservedBy([PublishableContentObserver::class, EntradaAuthorshipObserver::class])]
#[Fillable([
    'mam',
    'espanol',
    'definicion',
    'categoria_gramatical_id',
    'municipio',
    'fuente_id',
    'pagina_fuente',
])]
class Entrada extends Model
{
    /** @use HasFactory<EntradaFactory> */
    use HasFactory;

    /**
     * Toda entrada nace sin revisar.
     *
     * La base ya lo impone con un `default false`, pero el modelo recién creado en memoria
     * no lo sabría y respondería `null` en el JSON del panel. Declararlo aquí también deja
     * el invariante escrito donde se lee, y no solo en la migración.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'revisado' => false,
    ];

    protected function casts(): array
    {
        return [
            'revisado' => 'boolean',
            'revisado_en' => 'datetime',
        ];
    }

    /**
     * Normaliza el lema y deriva de él las dos columnas de ordenamiento y búsqueda.
     *
     * Un setter de Eloquent puede devolver varios atributos a la vez, que es exactamente lo
     * que hace falta: las tres columnas son una sola verdad y separarlas en tres mutators
     * abriría la puerta a que quedaran descoordinadas.
     *
     * Las derivadas se calculan sobre el lema **ya normalizado**. Si se generaran sobre lo
     * que llegó, `õiky` se tokenizaría como carácter desconocido y ordenaría con `00` en
     * lugar de con la posición 30 de `ẍ`.
     */
    protected function mam(): Attribute
    {
        return Attribute::set(function (?string $value): array {
            $normalizado = TextNormalizer::normalize((string) $value);

            return [
                'mam' => $normalizado,
                'busqueda' => TextNormalizer::searchKey($normalizado),
                'orden_alfabetico' => app(SortKeyGenerator::class)->generate($normalizado),
            ];
        });
    }

    /**
     * El orden alfabético del Mam, que no es el del castellano.
     *
     * Existe para que nadie tenga que acordarse de la regla: `ORDER BY mam` daría un orden
     * equivocado y en el par `xaq`/`ẍiky` ni siquiera uno estable, porque para
     * `utf8mb4_unicode_ci` `x` y `ẍ` son la misma letra.
     *
     * El desempate por `id` no es cosmético. Los homógrafos son legítimos —la misma forma
     * con distinta acepción o distinta fuente— y comparten clave de intercalación, y MySQL
     * no promete ningún orden entre filas empatadas. Sin desempate pasan dos cosas feas:
     * al paginar seis mil entradas, una puede salir dos veces o no salir en ninguna página;
     * y la exportación produciría un JSON distinto en cada compilación aunque no haya
     * cambiado nada, lo que ensucia los diffs y engaña al Service Worker de los clientes.
     */
    #[Scope]
    protected function enOrdenMam(Builder $query): void
    {
        $query->orderBy('orden_alfabetico')->orderBy('id');
    }

    /**
     * Busca contra la columna derivada, nunca contra `mam`.
     *
     * El término se pasa por la misma función que generó la columna, así que quien teclea
     * `qaq`, `q'aq'` o `Q’AQ’` encuentra lo mismo. Ningún estudiante escribe el saltillo.
     */
    #[Scope]
    protected function buscar(Builder $query, string $termino): void
    {
        $clave = TextNormalizer::searchKey($termino);

        if ($clave === '') {
            return;
        }

        $query->where('busqueda', 'like', '%'.$clave.'%');
    }

    #[Scope]
    protected function revisadas(Builder $query): void
    {
        $query->where('revisado', true);
    }

    public function categoriaGramatical(): BelongsTo
    {
        return $this->belongsTo(CategoriaGramatical::class);
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(Fuente::class);
    }

    /** Quién la cargó. Nulo si entró por importación o por seeder, sin sesión abierta. */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Quién dio fe de que el Mam está bien escrito. Nulo mientras no esté revisada. */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function categorias(): BelongsToMany
    {
        // Tabla declarada a mano: la convención de Laravel ordena los nombres en inglés y
        // daría `categoria_entrada`, no el `entrada_categoria` del modelo de datos.
        return $this->belongsToMany(Categoria::class, 'entrada_categoria');
    }
}
