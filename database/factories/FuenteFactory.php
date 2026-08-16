<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Fuente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fuente>
 */
class FuenteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titulo' => fake()->unique()->sentence(4),
            'institucion' => fake()->randomElement(['ALMG', 'MINEDUC', 'DIGEBI', 'DIDEDUC San Marcos']),
            'anio' => fake()->numberBetween(2005, 2020),
            'licencia' => 'Reproducción sin fines comerciales citando la fuente',
            'url' => null,
        ];
    }
}
