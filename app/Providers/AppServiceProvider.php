<?php

namespace App\Providers;

use App\Support\Mam\Alphabet;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
