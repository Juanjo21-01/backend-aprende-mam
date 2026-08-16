<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temas del vocabulario: saludos, familia, escuela, animales, colores, el mercado.
 *
 * Jerárquica por `padre_id` porque los temas del módulo L2 se agrupan («la naturaleza»
 * contiene «animales» y «plantas»), y porque el sitio público navega por temas antes que
 * alfabéticamente: es la puerta de entrada de una estudiante de primaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            $table->string('nombre_es', 120)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Texto en Mam: pasa por el normalizador en el mutator del modelo, igual que
            // `entradas.mam`. Es el nombre del tema en el idioma, no una traducción suelta.
            $table->string('nombre_mam', 120)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // ASCII por construcción; lo consume la URL del sitio estático.
            $table->string('slug', 140)
                ->unique()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->string('icono', 60)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // El orden de los temas lo decide el docente, no el alfabeto.
            $table->unsignedSmallInteger('orden')->default(0);

            // `nullOnDelete` y no `cascade`: borrar un tema padre no puede llevarse por
            // delante sus hijos y, con ellos, la clasificación de las palabras que cuelgan.
            $table->foreignId('padre_id')
                ->nullable()
                ->constrained('categorias')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
