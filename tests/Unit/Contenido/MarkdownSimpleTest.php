<?php

declare(strict_types=1);

namespace Tests\Unit\Contenido;

use App\Support\Contenido\Enlaces;
use App\Support\Contenido\MarkdownSimple;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarkdownSimpleTest extends TestCase
{
    public function test_acepta_markdown_simple(): void
    {
        $cuerpo = <<<'MD'
        ## Los cuatro pueblos

        Texto con **negritas**, _cursiva_ y una lista:

        - Uno
        - Dos

        Más en [la ALMG](https://www.almg.org.gt/), en [otra página](/cultura/idioma-mam)
        y [al inicio](#inicio).

        ![Mapa de San Marcos](/img/mapa.png)
        ![Foto](https://cdn.example.org/foto.jpg)
        MD;

        $this->assertNull(MarkdownSimple::problema($cuerpo));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cuerposRechazados(): array
    {
        return [
            'etiqueta html' => ['Hola <b>mundo</b>'],
            'script' => ['<script>alert(1)</script>'],
            'iframe pegado' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'],
            'comentario html' => ['Texto <!-- oculto -->'],
            'autoenlace' => ['Ver <https://example.org>'],
            'enlace javascript' => ['[clic](javascript:alert(1))'],
            'enlace http' => ['[clic](http://example.org)'],
            'enlace relativo al protocolo' => ['[clic](//example.org)'],
            'enlace data' => ['[clic](data:text/html;base64,AAAA)'],
            'imagen http' => ['![x](http://example.org/a.png)'],
            'imagen con ancla' => ['![x](#a)'],
            'imagen javascript' => ['![x](javascript:alert(1))'],
            'referencia peligrosa' => ["[a][1]\n\n[1]: javascript:alert(1)"],
        ];
    }

    #[DataProvider('cuerposRechazados')]
    public function test_rechaza_html_y_destinos_inseguros(string $cuerpo): void
    {
        $this->assertNotNull(MarkdownSimple::problema($cuerpo));
    }

    public function test_un_signo_menor_de_matematicas_no_es_html(): void
    {
        $this->assertNull(MarkdownSimple::problema('Si 3 < 5 entonces hay más.'));
    }

    public function test_enlaces_acepta_https_y_rutas_del_sitio(): void
    {
        $this->assertTrue(Enlaces::esDestinoSeguro('https://example.org/a'));
        $this->assertTrue(Enlaces::esDestinoSeguro('/img/a.png'));
        $this->assertFalse(Enlaces::esDestinoSeguro('//example.org/a'));
        $this->assertFalse(Enlaces::esDestinoSeguro('http://example.org/a'));
        $this->assertFalse(Enlaces::esDestinoSeguro('https://'));
        $this->assertFalse(Enlaces::esDestinoSeguro('#inicio'));
        $this->assertTrue(Enlaces::esDestinoSeguro('#inicio', permiteAncla: true));
    }

    public function test_el_archivo_pdf_exige_extension_pdf(): void
    {
        $this->assertNull(Enlaces::problemaDeArchivoPdf('/materiales/guia.pdf'));
        $this->assertNull(Enlaces::problemaDeArchivoPdf('https://cdn.example.org/guia.PDF?v=2'));
        $this->assertNotNull(Enlaces::problemaDeArchivoPdf('/materiales/guia.docx'));
        $this->assertNotNull(Enlaces::problemaDeArchivoPdf('http://cdn.example.org/guia.pdf'));
        $this->assertNotNull(Enlaces::problemaDeArchivoPdf('guia.pdf'));
        // Google Drive no termina en `.pdf`, pero es donde el docente sube los suyos.
        $this->assertNull(Enlaces::problemaDeArchivoPdf('https://drive.google.com/file/d/ABC/view'));
        $this->assertNull(Enlaces::problemaDeArchivoPdf('https://drive.google.com/uc?export=download&id=ABC'));
        $this->assertNotNull(Enlaces::problemaDeArchivoPdf('http://drive.google.com/file/d/ABC/view'));
        $this->assertNotNull(Enlaces::problemaDeArchivoPdf('https://drive.google.com.evil.org/file/d/ABC/view'));
        $this->assertNotNull(Enlaces::problemaDeArchivoPdf('//cdn.example.org/guia.pdf'));
    }
}
