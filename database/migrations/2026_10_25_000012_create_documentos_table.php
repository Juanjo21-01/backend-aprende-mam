<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materiales descargables: guías, fichas, cuadernillos e infografías.
 *
 * Aquí viven los **metadatos**; el PDF no. `archivo_url` es una ruta del sitio o una URL
 * https, y el archivo se coloca en el frontend o en un CDN. El motivo es el modelo de
 * despliegue: el cPanel compartido no sirve descargas de estudiantes, y los PDF se bajan
 * bajo demanda, no se precachean en el teléfono.
 *
 * Sin contador de descargas, a diferencia de lo que anota `docs/modelo-datos.md`: el sitio
 * es estático y no vuelve a llamar a este backend, así que no habría quien lo incrementara.
 *
 * Mismo ciclo de vida que `paginas` y `entradas`: nace sin revisar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            $table->string('slug', 140)
                ->unique()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Normalizados como los de `paginas`: el título de una guía suele llevar Mam.
            $table->string('titulo', 191)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->text('descripcion')
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Cadena y no `enum` de MySQL; la lista cerrada la impone `TipoDocumento`.
            $table->string('tipo', 30)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->string('archivo_url', 500)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Para decirle a quien tiene 3G cuánto va a bajar antes de que toque el botón.
            $table->unsignedInteger('tamano_kb')->nullable();

            $table->string('licencia', 191)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->string('video_url', 500)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->foreignId('fuente_id')
                ->nullable()
                ->constrained('fuentes')
                ->nullOnDelete();

            $table->unsignedSmallInteger('orden')->default(0);

            $table->boolean('revisado')->default(false)->index();

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
