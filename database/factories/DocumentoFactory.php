<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoDocumento;
use App\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3);

        return [
            'slug' => $slug,
            'titulo' => fake()->sentence(3),
            'descripcion' => fake()->sentence(),
            'tipo' => TipoDocumento::Guia,
            'archivo_url' => "/materiales/{$slug}.pdf",
        ];
    }

    /** Solo lo revisado llega al sitio público, así que el export se prueba con esto. */
    public function revisado(): static
    {
        return $this->state(fn (array $attributes) => ['revisado' => true]);
    }
}
