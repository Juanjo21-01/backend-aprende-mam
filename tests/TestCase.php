<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * La suite corre contra MySQL, no SQLite, así que `RefreshDatabase` borra una base
     * real. Si `DB_DATABASE` se filtrara del `.env` apuntaría a `aprende_mam` y el
     * primer test se llevaría por delante el diccionario. phpunit.xml ya lo fija con
     * `force="true"`; esto es el segundo cerrojo, y corre antes de que el trait toque
     * nada.
     */
    protected function setUp(): void
    {
        $database = $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '';

        if (! str_ends_with((string) $database, '_testing')) {
            throw new RuntimeException(
                "Los tests apuntan a la base «{$database}», que no termina en «_testing». ".
                'Abortado para no borrar datos de desarrollo. Revisar phpunit.xml.'
            );
        }

        parent::setUp();
    }
}
