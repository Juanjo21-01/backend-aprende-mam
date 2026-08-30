<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VersionContenido;
use App\Support\Publishing\PublicationScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «Lo que guardé, ¿ya está en el sitio?»
 *
 * Es la única pregunta del panel que no se contesta mirando contenido, y hasta ahora no se
 * contestaba de ninguna manera: había que entrar al servidor a leer el log. Quien carga
 * vocabulario no es programador, y sin esta respuesta el sistema se vuelve opaco justo en
 * el punto donde su trabajo se hace público.
 *
 * Lo leen **los dos roles**. Un editor no publica ni configura nada, pero acaba de cargar
 * cuarenta palabras y tiene el mismo derecho a saber si salieron.
 *
 * Tres estados y ninguno más:
 *
 * - **Sin configurar.** No hay `DEPLOY_HOOK_URL`, así que no se encola nada y no se va a
 *   publicar solo. Es lo normal en desarrollo, y en producción es un error de instalación
 *   que conviene ver en pantalla y no descubrir meses después.
 * - **Al día.** Lo publicado alcanza a lo que hay.
 * - **Con cambios sin publicar.** Y entonces importa *cuándo*: si la hora prevista ya pasó
 *   y sigue sin publicarse, lo más probable es que no haya un trabajador de cola corriendo.
 *   Ese fallo es mudo —los trabajos se apilan en `jobs` sin que nadie se entere—, y esta es
 *   la única superficie donde se puede ver.
 */
final class PublicacionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $version = VersionContenido::actual();
        $programada = PublicationScheduler::programadaPara();

        $publicado = $version->publicado_numero;
        $sinPublicar = $publicado === null || $publicado < $version->numero;

        return response()->json([
            'version' => $version->numero,

            // Nulo significa «nunca se publicó», que es el estado real de una instalación
            // recién desplegada. No es un dato que falte.
            'version_publicada' => $publicado,
            'publicado_en' => $version->publicado_en,

            'sin_publicar' => $sinPublicar,

            // Sin hook no hay a quién avisar: el panel tiene que decirlo en vez de dejar
            // «pendiente» para siempre, que parecería una publicación que nunca llega.
            'habilitada' => PublicationScheduler::isEnabled(),

            'retardo_segundos' => PublicationScheduler::delaySeconds(),

            // Solo mientras haya algo pendiente: una hora vieja de una publicación que ya
            // salió confundiría más de lo que explica.
            'programada_para' => $sinPublicar ? $programada : null,
        ]);
    }
}
