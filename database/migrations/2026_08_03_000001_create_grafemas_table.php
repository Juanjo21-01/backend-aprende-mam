<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo del alfabeto Mam: 32 grafemas en orden de intercalación.
 *
 * Vive en base de datos y no como constante en código para que un ajuste
 * ratificado por COLIMAM no obligue a tocar la implementación. Lo consume el
 * tokenizador (coincidencia más larga primero) y la generación de
 * `orden_alfabetico`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grafemas', function (Blueprint $table) {
            // Declarados explícitamente: no se hereda nada del servidor ni del esquema.
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // 1–32. Es la clave real del catálogo y la que define el orden de
            // intercalación; de aquí sale la clave de dos dígitos de `orden_alfabetico`.
            $table->unsignedTinyInteger('posicion')->unique();

            // Máximo 3 caracteres: las glotalizadas compuestas (`ch'`, `ky'`, `tx'`, `tz'`).
            // Son 3 caracteres, no 3 bytes: en utf8mb4 `ẍ` ocupa 2 bytes y el
            // saltillo U+2019 ocupa 3.
            //
            // Sin índice único a propósito. utf8mb4_unicode_ci ignora los acentos,
            // así que para MySQL `x` (29) y `ẍ` (30) son la misma cadena y un UNIQUE
            // rechazaría la posición 30 al sembrar. La unicidad la garantiza
            // `posicion`. Por lo mismo, nunca buscar un grafema con
            // `where('grafema', ...)`: usar `posicion`.
            $table->string('grafema', 3)
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            $table->enum('tipo', [
                'vocal',
                'consonante_simple',
                'consonante_compuesta',
                'glotalizada',
                'saltillo',
            ])->charset('utf8mb4')->collation('utf8mb4_unicode_ci');

            // Dígrafo en sentido estricto: se escribe con dos letras del castellano
            // pero es una sola letra del Mam (`ch`, `ky`, `tx`, `tz`). Las glotalizadas
            // no cuentan como dígrafos aunque también ocupen más de un carácter.
            $table->boolean('es_digrafo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grafemas');
    }
};
