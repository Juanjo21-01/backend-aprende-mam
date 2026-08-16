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
     * Lemas reales del corpus, no palabras inventadas por Faker.
     *
     * Importa: un lema en castellano no ejercitaría ni los dígrafos ni las glotalizadas ni
     * `ẍ`, que es justo lo que puede romperse. Estos cubren los tres casos y salen de los
     * vectores de `.claude/rules/ortografia-mam.md`.
     *
     * @var list<array{string, string}> mam, español
     */
    private const LEMAS = [
        ['chmol', 'reunión'],
        ['b’aq', 'hueso'],
        ['tz’is', 'basura'],
        ['jal', 'mazorca'],
        ['ch’el', 'chocoyo'],
        ['kyaje', 'cuatro'],
        ['tzaj', 'ocote'],
        ['k’um', 'ayote'],
        ['ẍiky', 'conejo'],
        ['xaq', 'piedra'],
        ['q’aq’', 'fuego'],
        ['tx’otx’', 'tierra'],
        ['kyiẍ', 'pez; pescado'],
        ['b’ib’itz', 'susurro'],
        ['jwe’', 'cinco'],
        ['a’witz', 'cerro'],
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
