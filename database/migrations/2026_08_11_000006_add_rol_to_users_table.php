<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los dos únicos roles del sistema.
 *
 * No hay tabla de roles ni de permisos: son dos, no van a crecer y el proyecto lo mantiene
 * una sola persona. La diferencia entre ambos la resuelven las políticas de Laravel.
 *
 * `editor` por defecto, que es el rol de menor privilegio: si alguna vez se crea un usuario
 * sin especificar el rol, el fallo seguro es que no pueda borrar ni marcar como revisado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['administrador', 'editor'])
                ->default('editor')
                ->after('password')
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
