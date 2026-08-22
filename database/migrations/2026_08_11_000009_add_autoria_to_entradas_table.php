<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién cargó cada palabra y quién dio fe de que está bien escrita.
 *
 * El sistema tiene dos roles justamente para separar quién carga de quién aprueba, y hasta
 * ahora guardaba **que** una entrada estaba revisada pero no por quién ni cuándo. Para un
 * proyecto donde la trazabilidad del contenido es requisito académico, esa mitad faltaba.
 *
 * Es un dato que no se puede reconstruir hacia atrás: si se añade después de que el docente
 * cargue tres mil palabras, esas tres mil quedan sin autor para siempre.
 *
 * Las tres son anulables porque el importador del corpus y los seeders escriben sin sesión
 * abierta, y porque una entrada sin revisar no tiene revisor todavía. `nullOnDelete` es
 * defensa en profundidad: el panel no borra cuentas, las desactiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entradas', function (Blueprint $table) {
            $table->foreignId('creado_por')
                ->nullable()
                ->after('revisado')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('revisado_por')
                ->nullable()
                ->after('creado_por')
                ->constrained('users')
                ->nullOnDelete();

            // `updated_at` no sirve para esto: cambia con cualquier corrección posterior.
            // La fecha de la revisión tiene que ser suya y no moverse.
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
        });
    }

    public function down(): void
    {
        Schema::table('entradas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creado_por');
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn('revisado_en');
        });
    }
};
