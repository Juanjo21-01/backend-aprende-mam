<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entrada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entrada>
 */
class EntradaFactory extends Factory
{
    /**
     * Lemas reales del corpus **con glosas verificadas contra la fuente**.
     *
     * Importa por dos motivos. El primero es técnico: un lema en castellano no ejercitaría
     * ni los dígrafos ni las glotalizadas ni `ẍ`, que es justo lo que puede romperse.
     *
     * El segundo es de integridad, y costó descubrirlo. Una versión anterior de esta lista
     * traía significados escritos de memoria: `chmol` figuraba como «reunión» cuando el
     * COLIMAM dice **araña**, y `a’witz` como «cerro» cuando dice **hoja**. Tres de catorce
     * estaban mal. En un proyecto cuyo objeto es preservar un idioma, un dato inventado en
     * un fixture es un dato inventado que alguien puede copiar.
     *
     * **Regla: no agregar un lema a esta lista sin haberlo leído en una fuente.** Las glosas
     * de abajo salen del Diccionario Mam de COLIMAM (ALMG) salvo las seis marcadas, que
     * salen del Manual de Normas Ortográficas del propio proyecto — el COLIMAM las imprime
     * con la `ẍ` corrompida en `õ` y no se pueden citar de ahí sin ambigüedad.
     *
     * @var list<array{string, string}> mam, español
     */
    private const LEMAS = [
        // Diccionario Mam, COLIMAM (ALMG).
        ['chmol', 'araña'],
        ['b’aq', 'hueso'],
        ['tz’is', 'basura'],
        ['jal', 'mazorca'],
        ['ch’el', 'chocoyo; perica'],
        ['tzaj', 'pino'],
        ['xaq', 'piedra'],
        ['q’aq’', 'fuego'],
        ['tx’otx’', 'tierra'],
        ['jwe’', 'cinco'],
        ['a’witz', 'hoja'],
        ['tx’ajol', 'lavar'],
        ['pa’ch', 'cuache'],

        // Manual de Normas Ortográficas — AprendeMam. Las seis palabras con `ẍ`.
        ['ẍiky', 'conejo'],
        ['kyiẍ', 'pez; pescado'],
        ['wiẍ', 'gato'],
        ['i’ẍ', 'elote'],
        ['ẍoq’', 'tinaja'],
        ['napẍ', 'nabo'],
    ];

    public function definition(): array
    {
        [$mam, $espanol] = fake()->randomElement(self::LEMAS);

        return [
            'mam' => $mam,
            'espanol' => $espanol,

            // `busqueda` y `orden_alfabetico` no se declaran aquí: las deriva el mutator.
            // Ponerlas sería justamente el error que el modelo existe para impedir.
        ];
    }

    /** Solo lo revisado llega al sitio público, así que el export se prueba con esto. */
    public function revisada(): static
    {
        return $this->state(fn (array $attributes) => [
            'revisado' => true,
        ]);
    }

    public function conLema(string $mam): static
    {
        return $this->state(fn (array $attributes) => [
            'mam' => $mam,
        ]);
    }
}
