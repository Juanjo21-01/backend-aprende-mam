<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Cuentas del panel. Solo administradores.
 *
 * **No tiene `destroy`, y no es un olvido.** La baja de una cuenta es la bandera `activo`:
 * un borrado no se recupera y se lleva el rastro de quién trabajó en el contenido. Está en
 * `UserStatusController`.
 */
final class UserController extends Controller
{
    #[Authorize('viewAny', User::class)]
    public function index(): AnonymousResourceCollection
    {
        // Sin paginar: son tres o cuatro cuentas, no un directorio.
        $usuarios = User::query()
            ->orderByDesc('activo')
            ->orderBy('name')
            ->get();

        return UserResource::collection($usuarios);
    }

    #[Authorize('create', User::class)]
    public function store(UserRequest $request): UserResource
    {
        $usuario = new User($request->safe()->only('name', 'email', 'password'));

        // `rol` y `activo` no son asignables en masa: se ponen acá, explícitamente y
        // detrás de la política, para que ningún formulario pueda colarlos.
        $usuario->rol = UserRole::from((string) $request->validated('rol'));
        $usuario->activo = true;
        $usuario->save();

        return UserResource::make($usuario);
    }

    #[Authorize('view', 'usuario')]
    public function show(User $usuario): UserResource
    {
        return UserResource::make($usuario);
    }

    /**
     * Nombre, correo y rol. La contraseña no pasa por acá: tiene su propia acción, porque
     * cambiarla implica además cerrar las sesiones abiertas de esa persona.
     */
    #[Authorize('update', 'usuario')]
    public function update(UserRequest $request, User $usuario): UserResource
    {
        $usuario->fill($request->safe()->only('name', 'email'));

        if ($request->has('rol')) {
            $usuario->rol = UserRole::from((string) $request->validated('rol'));
        }

        $usuario->save();

        return UserResource::make($usuario);
    }
}
