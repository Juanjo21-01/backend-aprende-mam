<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué versión se publicó, y cuándo.
 *
 * Hasta ahora el sistema sabía qué versión **tiene** el contenido, pero no cuál **salió**:
 * `DeployHook` solo dejaba una línea en el log. Con eso, la pregunta que de verdad se hace
 * quien carga vocabulario —«lo que guardé ayer, ¿ya está en el sitio?»— no tenía respuesta
 * dentro del panel, y para averiguarla había que entrar al servidor a leer archivos.
 *
 * Va en la misma fila que `numero` porque es el mismo hecho visto desde los dos lados: el
 * estado del conjunto publicable. Comparar las dos columnas da lo único que interesa —si hay
 * cambios sin publicar— sin ninguna consulta más.
 *
 * Ambas nulas al empezar, y es lo correcto: una instalación recién desplegada todavía no ha
 * publicado nada. Nulo significa «nunca», no «se perdió el dato».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versiones_contenido', function (Blueprint $table) {
            // La versión que el proveedor de alojamiento aceptó compilar. Puede quedarse
            // atrás de `numero`: eso es exactamente «hay cambios sin publicar».
            $table->unsignedBigInteger('publicado_numero')->nullable()->after('numero');

            $table->timestamp('publicado_en')->nullable()->after('publicado_numero');
        });
    }

    public function down(): void
    {
        Schema::table('versiones_contenido', function (Blueprint $table) {
            $table->dropColumn(['publicado_numero', 'publicado_en']);
        });
    }
};
