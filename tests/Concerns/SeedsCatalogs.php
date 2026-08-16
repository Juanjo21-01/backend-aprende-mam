<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Database\Seeders\DatabaseSeeder;

/**
 * Siembra los catálogos base antes de cada test: el alfabeto y las clases de palabra.
 *
 * No sirve `protected bool $seed = true` de `RefreshDatabase`: eso solo siembra durante el
 * `migrate:fresh` inicial, que corre **una vez por proceso** y lo dispara la primera clase
 * de test que toque la base. Si esa primera clase no pide semilla —las de `tests/Feature/Mam`
 * siembran a mano—, el resto de la suite se queda sin catálogo y el mutator de `Entrada`
 * revienta con «El catálogo `grafemas` está vacío». Sembrar en `setUp` no depende del orden.
 *
 * Laravel invoca `setUp{NombreDelTrait}` solo, después de `RefreshDatabase`.
 */
trait SeedsCatalogs
{
    protected function setUpSeedsCatalogs(): void
    {
        $this->seed(DatabaseSeeder::class);
    }
}
