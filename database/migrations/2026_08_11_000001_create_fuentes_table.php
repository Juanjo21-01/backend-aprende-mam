<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad bibliográfica: de qué libro y de qué página salió cada entrada.
 *
 * El proyecto cubre solo San Marcos y ninguna consulta del sitio público necesita esta
 * tabla, pero poder demostrar la procedencia de cada palabra es requisito académico del
 * proyecto de graduación y respaldo ante cualquier objeción sobre el léxico. El costo hoy
 * es cero; añadirla después de cargar seis mil entradas costaría una migración de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuentes', function (Blueprint $table) {
            // Declarados explícitamente: no se hereda nada del servidor ni del esquema.
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // «Diccionario Mam», «Módulo autoformativo Tu’jil Qyol Mam te Tkab’in Yol».
            // Los títulos del corpus vienen en Mam, así que utf8mb4 no es decorativo aquí.
            $table->string('titulo')
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // ALMG, MINEDUC, DIGEBI, DIDEDUC San Marcos.
            $table->string('institucion', 191)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->unsignedSmallInteger('anio')->nullable();

            // Varias de las fuentes permiten reproducción sin fines comerciales citando el
            // origen. Dejarlo registrado por entrada evita tener que reconstruirlo después.
            $table->string('licencia', 191)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->string('url', 500)
                ->nullable()
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuentes');
    }
};
