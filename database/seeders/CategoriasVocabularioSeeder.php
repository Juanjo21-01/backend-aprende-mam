<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\VersionContenido;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Los temas contra los que se clasifica el vocabulario importado (Fase 4).
 *
 * Los siete primeros son los que ya sembraba `VocabularioDemoSeeder`, con los mismos slugs y
 * el mismo orden; los otros nueve hacen falta en cuanto el listado pasa de cuarenta palabras a
 * miles. Todos de primer nivel: la jerarquía se decide cuando haya contenido que la pida.
 *
 * ## Qué no hace
 *
 * - **No pone `nombre_mam`.** Los nombres en Mam los confirma el validador lingüístico en el
 *   panel. Las categorías salen en la exportación y no tienen bandera de revisión, así que un
 *   nombre sembrado aquí llegaría al sitio sin que nadie lo hubiera revisado.
 * - **No pisa nada.** `firstOrCreate` por `slug`: si el docente ya renombró o reordenó un tema
 *   en el panel, se queda como lo dejó. Por eso se puede correr en producción.
 *
 * No va en `DatabaseSeeder`: ese siembra los catálogos ortográficos de los que depende todo
 * —también cada test—, y los temas son contenido. Se corre a mano:
 * `php artisan db:seed --class=CategoriasVocabularioSeeder`.
 */
class CategoriasVocabularioSeeder extends Seeder
{
    /**
     * Sin eventos: dieciséis altas serían dieciséis incrementos de versión y otros tantos
     * trabajos de publicación. La versión se sube una vez al final.
     */
    use WithoutModelEvents;

    /** @var array<string, string> slug => nombre en castellano, en el orden del panel */
    private const TEMAS = [
        'animales' => 'Animales',
        'la-familia' => 'La familia',
        'colores' => 'Colores',
        'numeros' => 'Números',
        'la-naturaleza' => 'La naturaleza',
        'la-casa' => 'La casa',
        'alimentos' => 'Alimentos',
        'el-cuerpo' => 'El cuerpo',
        'ropa-y-tejido' => 'Ropa y tejido',
        'la-escuela' => 'La escuela',
        'oficios-y-trabajo' => 'Oficios y trabajo',
        'plantas-y-cultivos' => 'Plantas y cultivos',
        'tiempo-y-calendario' => 'Tiempo y calendario',
        'la-comunidad' => 'La comunidad',
        'acciones' => 'Acciones',
        'cualidades' => 'Cualidades',
    ];

    public function run(): void
    {
        $creados = 0;
        $orden = 0;

        foreach (self::TEMAS as $slug => $nombre) {
            $orden++;

            $tema = Categoria::firstOrCreate(
                ['slug' => $slug],
                ['nombre_es' => $nombre, 'orden' => $orden]
            );

            if ($tema->wasRecentlyCreated) {
                $creados++;
            }
        }

        // El catálogo de temas sale completo en la exportación: un tema nuevo es contenido
        // nuevo aunque todavía no tenga palabras.
        if ($creados > 0) {
            VersionContenido::incrementar();
        }
    }
}
