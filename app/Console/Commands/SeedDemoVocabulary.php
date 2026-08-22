<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Entrada;
use Database\Seeders\VocabularioDemoSeeder;
use Illuminate\Console\Command;

/**
 * Carga o descarga el vocabulario de demostración.
 *
 * Su razón de ser es el frontend: sin datos no se puede maquetar un buscador ni una
 * navegación alfabética, y el diccionario de verdad todavía no está cargado. Las cuarenta y
 * dos palabras son reales y citadas —el detalle está en `VocabularioDemoSeeder`—, así que
 * ejercitan lo que de verdad puede romperse: dígrafos, glotalizadas, `ẍ`, lemas de dos
 * palabras y el orden del Mam, que no es el del castellano.
 */
class SeedDemoVocabulary extends Command
{
    protected $signature = 'mam:demo
                            {--limpiar : Borra el vocabulario de demostración}';

    protected $description = 'Carga (o descarga) el vocabulario de demostración para el frontend';

    public function handle(): int
    {
        if ($this->option('limpiar')) {
            $borradas = VocabularioDemoSeeder::limpiar();

            $this->info("Borradas {$borradas} entradas de demostración.");
            $this->line('Los temas y las fuentes se conservan si alguna entrada real los usa.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => VocabularioDemoSeeder::class, '--force' => true]);

        $total = Entrada::query()->count();
        $revisadas = Entrada::query()->where('revisado', true)->count();

        $this->newLine();
        $this->info("Vocabulario cargado: {$total} entradas, {$revisadas} revisadas.");
        $this->line('Ya sale por /api/v1/export/vocabulario.json con su token.');
        $this->newLine();
        $this->warn('Son datos de desarrollo: las palabras y sus glosas están citadas, pero');
        $this->warn('ninguna pasó por el validador lingüístico. No publicar así.');

        return self::SUCCESS;
    }
}
