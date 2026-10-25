<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contenido editorial del sitio: cultura, gramática y base legal.
 *
 * Una sola tabla con la sección como columna, y no una por sección: las tres son lo mismo
 * —un título y un cuerpo que el docente escribe en el panel— y la propuesta firmada dice que
 * el docente administra todo el contenido sin programador. Una cuarta sección es un valor
 * más del enum `SeccionPagina`, no una migración.
 *
 * El cuerpo es Markdown simple, no HTML: el contenido llega de un panel y el sitio es para
 * menores. Qué se admite lo comprueba `MarkdownSimple`; esta columna solo lo guarda.
 *
 * Mismo ciclo de vida que `entradas`: nace con `revisado = false` y solo el validador
 * lingüístico la marca. «No revisada» hace de borrador, así que no hay una bandera
 * `publicada` aparte que pueda contradecirla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paginas', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // Cadena y no `enum` de MySQL: añadir una sección no debería exigir un ALTER.
            // La lista cerrada la impone `SeccionPagina` en el modelo y en el request.
            $table->string('seccion', 30)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Va en la URL del sitio estático. Único **por sección**: «historia» puede
            // existir en cultura y en base legal sin chocar.
            $table->string('slug', 140)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Pasan por el normalizador del Mam: el título y el cuerpo mezclan castellano con
            // palabras y frases en Mam, y el apóstrofo recto o la `õ` corrupta no se ven.
            $table->string('titulo', 191)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->longText('cuerpo')
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // El orden de lectura lo decide el docente, igual que el de los temas. Nunca se
            // ordena por título: puede estar en Mam y el cotejamiento de MySQL no lo ordena.
            $table->unsignedSmallInteger('orden')->default(0);

            // URL de un video externo (YouTube, etc.) que se incrusta junto al texto. Nunca
            // se aloja. La validación espeja `src/data/incrustado.ts` del frontend.
            $table->string('video_url', 500)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Mismo requisito de trazabilidad que las entradas, y por la misma razón
            // anulable: una página escrita por el propio docente puede no tener fuente.
            $table->foreignId('fuente_id')
                ->nullable()
                ->constrained('fuentes')
                ->nullOnDelete();

            $table->boolean('revisado')->default(false)->index();

            // Autoría como en `add_autoria_to_entradas`. Anulables por lo mismo.
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();

            $table->timestamps();

            $table->unique(['seccion', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paginas');
    }
};
