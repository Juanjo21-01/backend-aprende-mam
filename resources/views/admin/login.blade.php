{{--
    Acceso al panel.

    Sin `@vite` a propósito: es la única pantalla que tiene que renderizar aunque el
    empaquetado de assets falte o esté roto, porque es la puerta desde la que se entra a
    arreglar todo lo demás. Son cuarenta líneas de CSS; no vale la pena atarlas a un build.

    Eso obliga a repetir aquí a mano la paleta de `resources/css/app.css`. Es duplicación
    consciente y es la única del proyecto: si allá se toca un tono, hay que tocarlo aquí.

    No carga Charis SIL, aunque el archivo esté en `public/fonts`: en esta pantalla no hay
    una sola palabra en Mam. La fuente se paga donde sirve.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a4f47">
    <title>Acceso · {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --papel: #fdfcf9;
            --tarjeta: #fff;
            --tinta: #1f1c19;
            --tinta-suave: #57504a;
            --borde: #e0dad0;
            --jade: #0f6f63;
            --alerta: #9a3412;
            /* La cenefa es verde oscuro en los dos temas, igual que la banda del panel. */
            --jade-hondo: #0a4f47;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --papel: #14120e;
                --tarjeta: #1c1a15;
                --tinta: #efeae0;
                --tinta-suave: #a89f90;
                --borde: #38332a;
                --jade: #4fb3a4;
                --alerta: #ea8f6d;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: var(--papel);
            color: var(--tinta);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        /* La cenefa del huipil, a media escala, igual que en el panel. Decoración: va en un
           elemento vacío y sin texto. */
        .cenefa {
            position: fixed;
            inset: 0 0 auto;
            height: 7px;
            background-color: var(--jade-hondo);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='14'%3E%3Cpath d='M8 1 15 7 8 13 1 7Z' fill='%23d99a1e'/%3E%3Cpath d='M8 4.5 10.5 7 8 9.5 5.5 7Z' fill='%230a4f47'/%3E%3Cpath d='M24 1 31 7 24 13 17 7Z' fill='%23b8452f'/%3E%3Cpath d='M24 4.5 26.5 7 24 9.5 21.5 7Z' fill='%230a4f47'/%3E%3Cpath d='M16 4 19 7 16 10 13 7Z' fill='%231f7f74'/%3E%3Cpath d='M0 4 3 7 0 10-3 7Z' fill='%231f7f74'/%3E%3Cpath d='M32 4 35 7 32 10 29 7Z' fill='%231f7f74'/%3E%3C/svg%3E");
            background-repeat: repeat-x;
            background-size: 16px 7px;
        }

        main {
            width: 100%;
            max-width: 22rem;
        }

        /* Una sola columna centrada: logo, nombre y leyenda, uno debajo de otro. Es la única
           pantalla del sistema donde no hay nada que hacer más que entrar, así que la marca
           puede ocupar el sitio que en el panel no tiene. */
        .marca {
            text-align: center;
        }

        /* El WebP es el logo circular sobre un cuadrado blanco: sin el recorte se ven las
           cuatro esquinas. */
        .marca img {
            display: block;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            margin: 0 auto 1rem;
        }

        h1 {
            font-size: 1.625rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        h1 span {
            color: #90610c;
        }

        @media (prefers-color-scheme: dark) {
            h1 span {
                color: #d99a1e;
            }
        }

        p.sub {
            margin-top: 0.25rem;
            color: var(--tinta-suave);
            font-size: 0.875rem;
        }

        form {
            margin-top: 1.75rem;
            background: var(--tarjeta);
            border: 1px solid var(--borde);
            border-radius: 0.75rem;
            padding: 1.5rem;
            display: grid;
            gap: 1rem;
        }

        label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.375rem;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font: inherit;
            color: inherit;
            background: var(--papel);
            border: 1px solid var(--borde);
            border-radius: 0.5rem;
        }

        :focus-visible {
            outline: 2px solid var(--jade);
            outline-offset: 2px;
        }

        .recordar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: var(--tinta-suave);
            font-weight: 400;
            margin-bottom: 0;
        }

        button {
            padding: 0.5rem 0.75rem;
            font: inherit;
            font-weight: 500;
            cursor: pointer;
            color: var(--papel);
            background: var(--jade);
            border: 0;
            border-radius: 0.5rem;
        }

        button:hover {
            background: var(--jade-hondo);
        }

        @media (prefers-color-scheme: dark) {
            button:hover {
                background: #6ac4b5;
            }
        }

        .error {
            color: var(--alerta);
            font-size: 0.8125rem;
        }

        ul.error {
            list-style: none;
            padding: 0;
            display: grid;
            gap: 0.25rem;
        }

        footer {
            margin-top: 1.25rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--tinta-suave);
        }
    </style>
</head>

<body>
    <div class="cenefa"></div>

    <main>
        {{-- `alt=""`: el nombre va escrito justo debajo. Con texto alternativo, un lector de
            pantalla anunciaría «AprendeMam AprendeMam». --}}
        <div class="marca">
            <img src="/logo.webp" alt="" width="96" height="96">
            <h1>Aprende<span>Mam</span></h1>
            <p class="sub">Panel de administración</p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            @if ($errors->any())
                <ul class="error" role="alert">
                    @foreach ($errors->all() as $mensaje)
                        <li>{{ $mensaje }}</li>
                    @endforeach
                </ul>
            @endif

            <div>
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    autocomplete="username">
            </div>

            <div>
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </div>

            <label class="recordar">
                <input type="checkbox" name="remember" value="1"> Mantener la sesión abierta
            </label>

            <button type="submit">Entrar</button>
        </form>

        <footer>Acceso restringido. Las cuentas las crea el administrador.</footer>
    </main>
</body>

</html>
