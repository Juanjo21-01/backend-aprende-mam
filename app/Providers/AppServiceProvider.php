<?php

namespace App\Providers;

use App\Support\Mam\Alphabet;
use App\Support\Mam\SortKeyGenerator;
use App\Support\Mam\Tokenizer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El alfabeto Mam se lee de la tabla `grafemas` una sola vez por proceso. Singleton y no
        // caché: no hay invalidación que mantener y una importación de 6,185 entradas no puede
        // hacer una consulta por palabra.
        $this->app->singleton(Alphabet::class);

        // Los dos de encima, por lo mismo: sin estado propio y en el camino caliente del
        // mutator de `Entrada`. Una importación de 6,185 entradas no puede reconstruirlos
        // en cada guardado.
        $this->app->singleton(Tokenizer::class);
        $this->app->singleton(SortKeyGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
