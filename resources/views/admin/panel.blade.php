{{--
    Cascarón del panel.

    Aquí no hay interfaz: la dibuja entera React desde `resources/js/app.tsx`, encabezado y
    botón de salir incluidos. Esta vista solo aporta las tres cosas que el JavaScript no se
    puede dar a sí mismo: la sesión ya comprobada por el middleware, el token CSRF para las
    escrituras, y un contenedor donde montar.

    El `<noscript>` no es cortesía. Esta pantalla solo se alcanza con sesión abierta, así que
    quien la vea en blanco ya entró bien y merece saber por qué no ve nada.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    {{-- Lo lee el cliente HTTP del panel para las escrituras a /api/v1/admin. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Panel · {{ config('app.name') }}</title>

    {{--
        Va antes de @vite y no es opcional: instala el preámbulo de Fast Refresh que
        @vitejs/plugin-react espera encontrar ya puesto cuando carga el primer módulo. Sin
        él, `npm run dev` muere con «can't detect preamble» señalando un archivo cualquiera
        del panel, que no tiene nada que ver.

        Con los assets compilados no hace nada —la directiva se calla si no hay servidor de
        Vite—, así que el fallo solo aparece en desarrollo.
    --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body class="min-h-screen bg-papel text-tinta antialiased">
    <div id="panel"></div>

    <noscript>
        <p style="padding: 1.5rem; font-family: system-ui, sans-serif;">
            El panel de administración necesita JavaScript. El sitio público de estudiantes y
            docentes no: eso se sirve como páginas estáticas desde otro dominio.
        </p>
    </noscript>
</body>
</html>
