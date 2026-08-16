<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entidad central del diccionario.
 *
 * `busqueda` y `orden_alfabetico` son columnas **derivadas**: las calcula el mutator de
 * `mam` en el modelo cada vez que el lema cambia. Nunca se aceptan desde el request ni se
 * editan a mano. Que vivan aquí y no se calculen al vuelo es lo que permite ordenar y
 * buscar con índice sobre seis mil entradas.
 *
 * Sin índice único sobre `mam` a propósito: los homógrafos son legítimos —la misma forma
 * con distinta acepción, distinta clase de palabra o distinta fuente— y además
 * `utf8mb4_unicode_ci` no distingue `x` de `ẍ`, así que un único rechazaría pares de
 * lemas que en Mam son palabras distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // Forma de despliegue, ya normalizada: apóstrofo U+2019 y `ẍ` en U+1E8D.
            // Indexada para que el panel pueda buscar un lema exacto al cargar; para el
            // buscador del editor y del sitio se usa `busqueda`, y para ordenar,
            // `orden_alfabetico`. Nunca `ORDER BY mam`.
            $table->string('mam', 191)
                ->index()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Acepciones separadas con punto y coma, según el Manual de Normas.
            $table->text('espanol')
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Definición extendida; la traen las entradas del COLIMAM.
            $table->text('definicion')
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->foreignId('categoria_gramatical_id')
                ->nullable()
                ->constrained('categorias_gramaticales')
                ->nullOnDelete();

            // Derivada. Minúsculas, sin apóstrofos, `ẍ`→`x`, sin diacríticos del
            // castellano: es contra lo que teclea quien busca.
            $table->string('busqueda', 191)
                ->index()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Derivada. Dos dígitos por grafema, así que 255 caracteres cubren un lema de
            // 127 grafemas. `SortKeyGenerator` ya revienta si una posición no cabe en dos
            // dígitos, que sería el único modo de que esta clave dejara de ordenar.
            $table->string('orden_alfabetico', 255)
                ->index()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Variación intradepartamental. El proyecto cubre San Marcos entero y el Mam
            // varía entre municipios; anulable porque casi ninguna fuente lo precisa.
            $table->string('municipio', 100)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // `nullOnDelete` en ambas: borrar una fuente o una clase de palabra no puede
            // llevarse por delante el léxico. Se pierde el dato accesorio, no la entrada.
            $table->foreignId('fuente_id')
                ->nullable()
                ->constrained('fuentes')
                ->nullOnDelete();

            // Rango («112-113») o romano, así que cadena y no entero.
            $table->string('pagina_fuente', 20)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Toda entrada nace sin revisar y solo el validador lingüístico la marca. Es lo
            // que permite cargar rápido y validar después sin perder el rastro, y es el
            // filtro del endpoint de exportación: al sitio público solo llega lo revisado.
            $table->boolean('revisado')->default(false)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas');
    }
};
