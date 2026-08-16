{{--
    Cascarón del panel.

    Aquí es donde monta la interfaz React de la siguiente tanda. Por ahora muestra quién
    entró y con qué rol, que es lo que hace verificable de punta a punta la capa de
    autenticación: si esta página se ve, la sesión funciona y el CRUD de `/api/v1/admin`
    responde con esa misma cookie.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    {{-- Lo lee el cliente HTTP de React para las peticiones a /api/v1/admin. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Panel · {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-[#1b1b18] antialiased dark:bg-[#0a0a0a] dark:text-[#ededec]">
    <header class="border-b border-[#e3e3e0] dark:border-[#3e3e3a]">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-4">
            <div>
                <p class="font-semibold">{{ config('app.name') }}</p>
                <p class="text-sm text-[#706f6c] dark:text-[#a1a09a]">
                    {{ auth()->user()->name }} · {{ auth()->user()->rol->label() }}
                </p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="rounded-md border border-[#e3e3e0] px-3 py-1.5 text-sm dark:border-[#3e3e3a]">
                    Salir
                </button>
            </form>
        </div>
    </header>

    {{-- Punto de montaje de la interfaz React. --}}
    <main id="panel" class="mx-auto max-w-5xl px-6 py-10">
        <h1 class="text-lg font-semibold">Panel en construcción</h1>
        <p class="mt-2 max-w-prose text-sm text-[#706f6c] dark:text-[#a1a09a]">
            La interfaz de administración se monta en este contenedor. El backend ya responde
            en <code>/api/v1/admin</code> con esta misma sesión: entradas, categorías, fuentes
            y el catálogo de clases de palabra.
        </p>
    </main>
</body>
</html>
