<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // La autorización de todo el panel se resuelve con políticas, y los controladores de
    // recurso las enganchan con `authorizeResource()`, que vive en este trait. Laravel ya no
    // lo trae de serie en el controlador base.
    use AuthorizesRequests;
}
