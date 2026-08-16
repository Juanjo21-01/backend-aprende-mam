<?php

declare(strict_types=1);

namespace App\Support\Publishing;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * El aviso al proveedor de alojamiento estático para que recompile el sitio.
 *
 * Es una sola petición: el hook no recibe el contenido, solo la señal de que hay algo
 * nuevo. El proveedor arranca la compilación de Astro, que a su vez viene a buscar el
 * vocabulario a `/api/v1/export/vocabulario.json` con su token.
 *
 * Funciona con los deploy hooks de Netlify y de Vercel, que son un POST a una URL secreta.
 * El número de versión viaja como título del disparo para que en el listado de despliegues
 * del proveedor se vea qué versión compiló cada uno; los proveedores que no lo entiendan
 * ignoran el cuerpo sin más.
 */
final class DeployHook
{
    private const TIMEOUT_SEGUNDOS = 15;

    public function isConfigured(): bool
    {
        return $this->url() !== '';
    }

    /**
     * Lanza si el proveedor responde mal, a propósito: así el trabajo lo reintenta y, si
     * se agotan los reintentos, queda registrado en `failed_jobs` en lugar de perderse.
     * Una publicación que falla en silencio es contenido que el docente cree publicado.
     */
    public function trigger(int $version): void
    {
        if (! $this->isConfigured()) {
            Log::info('Publicación omitida: no hay DEPLOY_HOOK_URL configurado.');

            return;
        }

        $respuesta = $this->post($version);

        if ($respuesta->failed()) {
            throw new RuntimeException(sprintf(
                'El deploy hook respondió %d al publicar la versión %d.',
                $respuesta->status(),
                $version
            ));
        }

        Log::info("Publicación disparada: versión {$version}.");
    }

    private function post(int $version): Response
    {
        return Http::timeout(self::TIMEOUT_SEGUNDOS)
            ->asJson()
            ->post($this->url(), [
                'trigger_title' => "AprendeMam · vocabulario v{$version}",
            ]);
    }

    private function url(): string
    {
        return (string) config('aprendemam.publicacion.deploy_hook_url');
    }
}
