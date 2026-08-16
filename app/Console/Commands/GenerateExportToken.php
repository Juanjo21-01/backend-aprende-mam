<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Genera un token para el endpoint de exportación.
 *
 * No escribe en `.env`: el archivo se edita a mano, y una escritura automática sobre el
 * archivo de configuración de producción es de las pocas cosas que pueden dejar la
 * instalación sin arrancar. El comando propone el valor y dice dónde va.
 */
class GenerateExportToken extends Command
{
    protected $signature = 'mam:token-exportacion';

    protected $description = 'Genera un token para el endpoint de exportación del vocabulario';

    public function handle(): int
    {
        $token = Str::random(64);

        $this->newLine();

        if (config('aprendemam.exportacion.token')) {
            $this->warn('Ya hay un EXPORT_TOKEN configurado. Si lo reemplazás, hay que');
            $this->warn('actualizarlo también en el proceso de compilación del sitio, o');
            $this->warn('la siguiente publicación fallará con 401.');
            $this->newLine();
        }

        $this->line('Copiá esta línea al archivo .env de la API:');
        $this->newLine();
        $this->line("  <options=bold>EXPORT_TOKEN={$token}</>");
        $this->newLine();
        $this->line('Y el mismo valor a la variable de entorno del proceso de compilación,');
        $this->line('que lo manda como cabecera:');
        $this->newLine();
        $this->line('  <options=bold>Authorization: Bearer '.$token.'</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
