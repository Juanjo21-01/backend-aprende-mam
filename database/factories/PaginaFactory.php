<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeccionPagina;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagina>
 */
class PaginaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seccion' => SeccionPagina::Cultura,
            'slug' => fake()->unique()->slug(3),
            'titulo' => fake()->sentence(3),
            'cuerpo' => "## Introducción\n\n".fake()->paragraph(),
        ];
    }

    /** Solo lo revisado llega al sitio público, así que el export se prueba con esto. */
    public function revisada(): static
    {
        return $this->state(fn (array $attributes) => ['revisado' => true]);
    }

    public function enSeccion(SeccionPagina $seccion): static
    {
        return $this->state(fn (array $attributes) => ['seccion' => $seccion]);
    }
}
