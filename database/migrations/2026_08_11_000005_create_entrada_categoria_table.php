<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote entre entradas y temas: una palabra puede estar en varios («ẍiky» es animal y
 * también aparece en el tema del mercado).
 *
 * `cascadeOnDelete` en las dos direcciones: una fila de este pivote no tiene sentido sin
 * sus dos extremos, y no guarda ningún dato propio que se pueda perder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrada_categoria', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->foreignId('entrada_id')->constrained('entradas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();

            // La clave compuesta es la que impide asignar dos veces el mismo tema a la
            // misma palabra; no hace falta un `id` que nadie va a referenciar.
            $table->primary(['entrada_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrada_categoria');
    }
};
