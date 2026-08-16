<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de clases de palabra, extraído por frecuencias del diccionario COLIMAM.
 *
 * `pos.` (posicional), `afect.` (afectivo), `dir.` (direccional) y `clas.` (clasificador)
 * son clases propias de las lenguas mayas y no tienen equivalente en la gramática del
 * castellano. Se conservan tal cual: reducirlas a las categorías del español empobrecería
 * el contenido y sería lingüísticamente incorrecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_gramaticales', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // `s.`, `v.t.`, `adj.` — tal como aparecen tras el separador `||` en el COLIMAM.
            // Es la clave por la que el importador del corpus va a buscar, de ahí el único.
            $table->string('abreviatura', 12)
                ->unique()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->string('nombre', 60)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Para las clases sin equivalente en castellano, que el módulo de gramática
            // tiene que explicar y el editor necesita entender al clasificar una palabra.
            $table->text('descripcion')
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_gramaticales');
    }
};
