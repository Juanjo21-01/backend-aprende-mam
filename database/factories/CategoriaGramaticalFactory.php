<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CategoriaGramatical;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaGramatical>
 *
 * En la práctica el catálogo se siembra con `CategoriasGramaticalesSeeder`, que es el que
 * trae las abreviaturas reales de ALMG. Esta factory existe para los tests que necesitan
 * una clase de palabra cualquiera sin arrastrar el catálogo entero.
 */
class CategoriaGramaticalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'abreviatura' => fake()->unique()->lexify('??.'),
            'nombre' => fake()->unique()->word(),
            'descripcion' => null,
        ];
    }
}
