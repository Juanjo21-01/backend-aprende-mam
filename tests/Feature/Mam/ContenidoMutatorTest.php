<?php

declare(strict_types=1);

namespace Tests\Feature\Mam;

use App\Enums\SeccionPagina;
use App\Models\Documento;
use App\Models\Pagina;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ningún texto en Mam entra a la base sin pasar por el normalizador, y las páginas y los
 * documentos también lo llevan: mezclan castellano con palabras y frases en Mam.
 *
 * Se prueba contra el modelo y no contra el endpoint, a propósito: el mutator tiene que
 * cubrir también seeders y tinker.
 */
final class ContenidoMutatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_normaliza_titulo_y_cuerpo(): void
    {
        $pagina = Pagina::create([
            'seccion' => SeccionPagina::Cultura,
            'slug' => 'saludos',
            'titulo' => "Q'anil y õiky",
            'cuerpo' => "Se dice **q'aq'** (fuego) y \u{02BC}ẍiky\u{A78C}; la õ es un error.",
        ]);

        $this->assertSame('Q’anil y ẍiky', $pagina->fresh()->titulo);
        $this->assertSame(
            'Se dice **q’aq’** (fuego) y ’ẍiky’; la ẍ es un error.',
            $pagina->fresh()->cuerpo
        );
    }

    public function test_la_pagina_deja_el_texto_en_nfc(): void
    {
        $pagina = Pagina::create([
            'seccion' => SeccionPagina::Gramatica,
            'slug' => 'letra',
            'titulo' => "La letra x\u{0308}",
            'cuerpo' => 'Texto.',
        ]);

        $this->assertSame('La letra ẍ', $pagina->fresh()->titulo);
    }

    public function test_el_documento_normaliza_titulo_y_descripcion(): void
    {
        $documento = Documento::create([
            'slug' => 'guia',
            'titulo' => "Guía de b'aq",
            'descripcion' => "Palabras con tz'is y õiky.",
            'tipo' => 'guia',
            'archivo_url' => '/materiales/guia.pdf',
        ]);

        $this->assertSame('Guía de b’aq', $documento->fresh()->titulo);
        $this->assertSame('Palabras con tz’is y ẍiky.', $documento->fresh()->descripcion);
    }

    public function test_una_descripcion_vacia_se_guarda_como_nula(): void
    {
        $documento = Documento::factory()->create(['descripcion' => '  ']);

        $this->assertNull($documento->fresh()->descripcion);
    }

    public function test_la_licencia_no_se_normaliza_porque_es_una_cita(): void
    {
        $documento = Documento::factory()->create(['licencia' => "Autorización de la ALMG (d'Alembert)"]);

        $this->assertSame("Autorización de la ALMG (d'Alembert)", $documento->fresh()->licencia);
    }

    public function test_el_video_pegado_como_iframe_se_guarda_como_url(): void
    {
        $pagina = Pagina::factory()->create([
            'video_url' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?a=1&amp;b=2"></iframe>',
        ]);

        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ?a=1&b=2',
            $pagina->fresh()->video_url
        );
    }

    public function test_un_video_vacio_se_guarda_como_nulo(): void
    {
        $this->assertNull(Pagina::factory()->create(['video_url' => ''])->fresh()->video_url);
        $this->assertNull(Documento::factory()->create(['video_url' => ' '])->fresh()->video_url);
    }

    public function test_nacen_sin_revisar(): void
    {
        $this->assertFalse(Pagina::factory()->create()->fresh()->revisado);
        $this->assertFalse(Documento::factory()->create()->fresh()->revisado);
    }

    public function test_el_slug_es_unico_por_seccion_en_la_base(): void
    {
        Pagina::factory()->enSeccion(SeccionPagina::Cultura)->create(['slug' => 'historia']);
        Pagina::factory()->enSeccion(SeccionPagina::BaseLegal)->create(['slug' => 'historia']);

        $this->expectException(QueryException::class);

        Pagina::factory()->enSeccion(SeccionPagina::Cultura)->create(['slug' => 'historia']);
    }
}
