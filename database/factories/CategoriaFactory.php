<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->unique()->words(2, true);

        return [
            'nombre_es' => Str::ucfirst($nombre),
            'nombre_mam' => null,
            'slug' => Str::slug($nombre),
            'orden' => fake()->numberBetween(0, 20),
        ];
    }

    public function hijaDe(Categoria $padre): static
    {
        return $this->state(fn (array $attributes) => [
            'padre_id' => $padre->id,
        ]);
    }
}
