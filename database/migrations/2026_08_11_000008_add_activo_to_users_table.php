<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baja lógica de una cuenta.
 *
 * El panel no borra usuarios y esta columna es el porqué. Una cuenta borrada no se
 * recupera y se lleva consigo el rastro de quién trabajó en el contenido; una desactivada
 * se vuelve a habilitar en un clic. Para un sistema con tres o cuatro cuentas, el borrado
 * no compra nada y puede costar caro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
