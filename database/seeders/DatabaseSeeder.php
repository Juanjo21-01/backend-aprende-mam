<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // Catálogo base: todo lo ortográfico depende de él.
            GrafemasSeeder::class,

            // Catálogo del que cuelga `entradas.categoria_gramatical_id`.
            CategoriasGramaticalesSeeder::class,
        ]);
    }
}
