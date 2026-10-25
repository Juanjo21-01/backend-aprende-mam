<?php

declare(strict_types=1);

namespace Tests\Unit\Contenido;

use App\Support\Contenido\EnlaceIncrustable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Las mismas reglas que `src/data/incrustado.ts` del frontend.
 *
 * Si este archivo y el del frontend dejan de coincidir, el panel acepta una dirección con la
 * que la compilación del sitio falla. Cada caso de aquí sale de un caso de allá.
 */
final class EnlaceIncrustableTest extends TestCase
{
    private const ID = 'dQw4w9WgXcQ';

    /**
     * @return array<string, array{string}>
     */
    public static function direccionesValidas(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v='.self::ID],
            'watch con inicio' => ['https://www.youtube.com/watch?v='.self::ID.'&t=1m30s'],
            'youtu.be' => ['https://youtu.be/'.self::ID],
            'embed' => ['https://www.youtube.com/embed/'.self::ID],
            'nocookie' => ['https://www.youtube-nocookie.com/embed/'.self::ID],
            'shorts' => ['https://www.youtube.com/shorts/'.self::ID],
            'live' => ['https://www.youtube.com/live/'.self::ID],
            'subdominio' => ['https://m.youtube.com/watch?v='.self::ID],
            'wordwall' => ['https://wordwall.net/resource/123/colores'],
            'educaplay' => ['https://es.educaplay.com/recursos-educativos/123-colores.html'],
        ];
    }

    #[DataProvider('direccionesValidas')]
    public function test_acepta_lo_que_el_frontend_sabe_incrustar(string $url): void
    {
        $this->assertNull(EnlaceIncrustable::problema($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function direccionesInvalidas(): array
    {
        return [
            'http' => ['http://www.youtube.com/watch?v='.self::ID],
            'dominio que solo contiene el nombre' => ['https://evilyoutube.com/watch?v='.self::ID],
            'dominio con el proveedor de prefijo' => ['https://youtube.com.evil.com/watch?v='.self::ID],
            'proveedor no permitido' => ['https://vimeo.com/123456'],
            'youtube sin identificador' => ['https://www.youtube.com/watch'],
            'youtube con identificador corto' => ['https://www.youtube.com/watch?v=abc'],
            'canal de youtube' => ['https://www.youtube.com/@canal'],
            'no es una direccion' => ['esto no es un enlace'],
            'javascript' => ['javascript:alert(1)'],
            'ruta del sitio' => ['/video.mp4'],
        ];
    }

    #[DataProvider('direccionesInvalidas')]
    public function test_rechaza_lo_que_haria_fallar_la_compilacion(string $url): void
    {
        $this->assertNotNull(EnlaceIncrustable::problema($url));
    }

    public function test_extrae_el_src_del_codigo_iframe_y_descarta_el_html(): void
    {
        $codigo = '<iframe width="560" src="https://www.youtube.com/embed/'.self::ID.'?a=1&amp;b=2" allowfullscreen></iframe>';

        $this->assertSame(
            'https://www.youtube.com/embed/'.self::ID.'?a=1&b=2',
            EnlaceIncrustable::extraer($codigo)
        );
        $this->assertNull(EnlaceIncrustable::problema($codigo));
    }

    public function test_un_iframe_con_un_proveedor_ajeno_se_rechaza(): void
    {
        $this->assertNotNull(
            EnlaceIncrustable::problema('<iframe src="https://evil.example/x"></iframe>')
        );
    }

    public function test_html_sin_iframe_se_rechaza(): void
    {
        $this->assertNotNull(EnlaceIncrustable::problema('<script>alert(1)</script>'));
    }

    public function test_una_direccion_mas_larga_que_la_columna_se_rechaza(): void
    {
        $this->assertNotNull(
            EnlaceIncrustable::problema('https://wordwall.net/'.str_repeat('a', 500))
        );
    }
}
