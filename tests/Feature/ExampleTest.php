<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * `/` ya no devuelve 200: este dominio no sirve contenido público —el sitio de
     * estudiantes se compila con Astro y vive en un CDN—, así que la raíz manda al panel.
     * Ver `AutenticacionTest::test_la_raiz_manda_al_panel`.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }
}
