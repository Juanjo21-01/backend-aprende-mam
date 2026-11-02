<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TITULO = 'Manual de Normas Ortográficas y Carga de Contenido';

    public function up(): void
    {
        DB::table('fuentes')
            ->where('titulo', self::TITULO)
            ->where('institucion', 'AprendeMam')
            ->update(['institucion' => 'Biblioteca Digital Mam']);
    }

    public function down(): void
    {
        DB::table('fuentes')
            ->where('titulo', self::TITULO)
            ->where('institucion', 'Biblioteca Digital Mam')
            ->update(['institucion' => 'AprendeMam']);
    }
};
