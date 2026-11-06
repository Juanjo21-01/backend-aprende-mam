<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un PDF adjunto a la página, junto al video. Lo usa la base legal: una ley o un acuerdo se
 * explica en el cuerpo y se adjunta el texto oficial.
 *
 * Mismos nombres y medidas que en `documentos`, porque es el mismo dato y el frontend lo
 * pinta con el mismo botón. Ambas anulables: las páginas que ya existen no llevan archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $table) {
            $table->string('archivo_url', 500)
                ->nullable()
                ->after('video_url')
                ->charset('utf8mb4')
                ->collation('utf8mb4_unicode_ci');

            // Para decirle a quien tiene 3G cuánto va a bajar antes de que toque el botón.
            $table->unsignedInteger('tamano_kb')->nullable()->after('archivo_url');
        });
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $table) {
            $table->dropColumn(['archivo_url', 'tamano_kb']);
        });
    }
};
