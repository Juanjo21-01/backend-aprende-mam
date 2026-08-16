<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishSite;
use App\Models\VersionContenido;
use App\Support\Publishing\DeployHook;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Dispara una publicación a mano, sin esperar el retardo.
 *
 * Para tres situaciones: la primera compilación del sitio, cuando todavía no hay contenido
 * nuevo que la dispare sola; después de una importación corrida con la publicación apagada;
 * y para reintentar cuando una publicación falló y hay que volver a lanzarla sin tocar
 * contenido solo para provocarla.
 */
class TriggerPublication extends Command
{
    protected $signature = 'mam:publicar
                            {--ahora : Ejecuta el aviso en el momento, sin pasar por la cola}';

    protected $description = 'Dispara la publicación del sitio estático sin esperar el retardo';

    public function handle(DeployHook $hook): int
    {
        $version = VersionContenido::numeroActual();

        if (! $hook->isConfigured()) {
            $this->error('No hay DEPLOY_HOOK_URL configurado: no hay a quién avisar.');
            $this->line('Ponelo en el .env con la URL del deploy hook de tu proveedor.');

            return self::FAILURE;
        }

        if (! config('aprendemam.publicacion.habilitada')) {
            $this->warn('La publicación automática está apagada (PUBLICACION_HABILITADA=false).');

            if ($this->input->isInteractive() && ! confirm('¿Publicar de todos modos?', default: true)) {
                return self::SUCCESS;
            }
        }

        $this->line("Versión del contenido: <options=bold>{$version}</>");

        // Sin testigo: esto no es una tanda de guardados, es una orden directa. No tiene
        // que retirarse aunque haya un trabajo con debounce esperando su turno.
        if ($this->option('ahora')) {
            $hook->trigger($version);
            $this->info('Aviso enviado al proveedor.');

            return self::SUCCESS;
        }

        PublishSite::dispatch();

        $this->info('Publicación encolada.');
        $this->line('Necesita un trabajador de cola corriendo: php artisan queue:work');

        return self::SUCCESS;
    }
}
