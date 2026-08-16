<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única con el número de versión del conjunto de datos publicable.
 *
 * El sitio público no consulta esta API: Astro compila el vocabulario a archivos estáticos.
 * Este número es lo que le dice al build si hay algo nuevo que compilar, y lo que el
 * Service Worker acaba viendo cambiar para renovar su caché.
 *
 * Es un contador y no una marca de tiempo derivada de `MAX(updated_at)` porque esa bajaría
 * al borrar la entrada más reciente, y una versión que retrocede deja a los clientes
 * convencidos de que ya tienen la última.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_contenido', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // Lo incrementa `ContentVersionObserver` con un `UPDATE ... numero = numero + 1`,
            // que es atómico: dos guardados simultáneos no se pisan.
            $table->unsignedBigInteger('numero')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones_contenido');
    }
};
