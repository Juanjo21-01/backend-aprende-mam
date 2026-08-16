{{--
    Acceso al panel.

    Sin `@vite` a propósito: es la única pantalla que tiene que renderizar aunque el
    empaquetado de assets falte o esté roto, porque es la puerta desde la que se entra a
    arreglar todo lo demás. Son treinta líneas de CSS; no vale la pena atarlas a un build.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --fondo: #fdfdfc; --tarjeta: #fff; --texto: #1b1b18; --tenue: #706f6c; --borde: #e3e3e0; --error: #b42318; --acento: #1b1b18; }
        @media (prefers-color-scheme: dark) {
            :root { --fondo: #0a0a0a; --tarjeta: #161615; --texto: #ededec; --tenue: #a1a09a; --borde: #3e3e3a; --error: #ff7b6b; --acento: #ededec; }
        }
        * { box-sizing: border-box; margin: 0; }
        body { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem;
               background: var(--fondo); color: var(--texto);
               font-family: system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.5; }
        main { width: 100%; max-width: 22rem; }
        h1 { font-size: 1.375rem; font-weight: 600; letter-spacing: -0.01em; }
        p.sub { color: var(--tenue); font-size: 0.875rem; margin-top: 0.25rem; }
        form { margin-top: 1.75rem; background: var(--tarjeta); border: 1px solid var(--borde);
               border-radius: 0.75rem; padding: 1.5rem; display: grid; gap: 1rem; }
        label { display: block; font-size: 0.8125rem; font-weight: 500; margin-bottom: 0.375rem; }
        input[type="email"], input[type="password"] {
            width: 100%; padding: 0.5rem 0.75rem; font: inherit; color: inherit;
            background: var(--fondo); border: 1px solid var(--borde); border-radius: 0.5rem; }
        input:focus { outline: 2px solid var(--acento); outline-offset: 1px; }
        .recordar { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--tenue); }
        button { padding: 0.5rem 0.75rem; font: inherit; font-weight: 500; cursor: pointer;
                 color: var(--fondo); background: var(--acento); border: 0; border-radius: 0.5rem; }
        .error { color: var(--error); font-size: 0.8125rem; }
        ul.error { list-style: none; padding: 0; display: grid; gap: 0.25rem; }
        footer { margin-top: 1.25rem; text-align: center; font-size: 0.75rem; color: var(--tenue); }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>
        <p class="sub">Panel de administración del contenido</p>

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
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       required autofocus autocomplete="username">
            </div>

            <div>
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password"
                       required autocomplete="current-password">
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
