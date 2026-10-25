/**
 * Encabezado, navegación y contenedor de las pantallas.
 *
 * La banda jade con el logo y la cenefa que la cierra son las mismas del sitio público, a
 * escala de herramienta: el docente que edita aquí y mira el sitio allá tiene que reconocer
 * que son la misma cosa. De ahí para abajo el panel es papel y tinta.
 *
 * EL ORO NO SE GASTA AQUÍ. En el sitio público la sección activa lleva filo dorado; en el
 * panel el oro significa una sola cosa —«revisado, esto lo va a ver un estudiante»— y
 * repartirlo por la navegación lo dejaría sin significado. La pestaña activa usa el mismo
 * filo de 3 px, en papel. La cenefa sí lleva sus rombos dorados, pero es textura de 7 px con
 * `aria-hidden`, no un elemento que informe de nada.
 *
 * El botón de salir es un `<form>` de verdad contra la ruta web `/admin/logout`, no un
 * `fetch`: cerrar sesión tiene que funcionar aunque el JavaScript del panel esté a medias,
 * y la respuesta es una redirección que el navegador sigue solo.
 *
 * «Cuentas» solo aparece para administradores. No es la seguridad —esa la hace `UserPolicy`
 * en el servidor— sino no ofrecer una pantalla que va a responder 403.
 */

import { NavLink, Outlet } from "react-router";

import { usePanel } from "../panel";
import { EstadoDePublicacion } from "./EstadoDePublicacion";

function tokenDelCascaron(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ""
    );
}

function claseDeEnlace({ isActive }: { isActive: boolean }): string {
    return [
        "relative inline-block py-2.5 text-sm whitespace-nowrap transition-colors",
        "after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:rounded-full after:transition-colors",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sobre-jade",
        isActive
            ? "font-semibold text-sobre-jade after:bg-sobre-jade"
            : "text-jade-claro after:bg-transparent hover:text-sobre-jade hover:after:bg-jade-claro/40",
    ].join(" ");
}

export function Layout() {
    const { sesion } = usePanel();

    return (
        <div className="min-h-screen bg-papel text-tinta">
            {/*
                Antes del contenido hay siete paradas de tabulador —logo, salir, cinco
                secciones y la franja de publicación— y se repiten en cada pantalla. Con esto
                son dos. Solo se ve al recibir el foco, que es cuando sirve.
            */}
            <a
                href="#contenido"
                className="sr-only rounded-lg focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-30 focus:bg-jade focus:px-4 focus:py-2 focus:text-papel"
            >
                Saltar al contenido
            </a>

            <header>
                <div className="bg-jade-hondo">
                    {/*
                        UNA FILA DESDE lg, DOS POR DEBAJO, Y UN SOLO CONTENEDOR PARA LAS DOS.

                        El DOM va en el orden que se ve en una fila —marca, secciones,
                        salir— para que el tabulador siga a la vista y no salte a «Salir»
                        antes que a las secciones. En dos filas, el `order-1` baja las
                        secciones a la segunda y su `w-full` la ocupa entera.

                        Cuándo es una y cuándo son dos lo decide la variante `una-fila`,
                        definida en `app.css`.

                        Con `order` y no duplicando el `<form>` de salir: dos formas serían dos
                        tokens CSRF y una parada de tabulador de mentira.
                    */}
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-2.5 px-4 pt-3 pb-2.5 sm:px-6 una-fila:flex-nowrap una-fila:gap-x-8">
                        <div className="flex min-w-0 items-center gap-3">
                            {/*
                                `alt=""`: el nombre del sitio va escrito al lado, dentro del
                                mismo bloque. Con texto alternativo, un lector de pantalla
                                anunciaría «AprendeMam AprendeMam».

                                `rounded-full` no es adorno: el WebP es el logo circular sobre
                                un cuadrado blanco, y sin el recorte se ven cuatro esquinas
                                blancas sobre el verde. El aro le da canto al disco blanco.
                            */}
                            <img
                                src="/logo.webp"
                                alt=""
                                width="36"
                                height="36"
                                className="size-9 shrink-0 rounded-full ring-1 ring-sobre-jade/25"
                            />

                            <div className="min-w-0">
                                <p className="text-xl leading-tight font-bold tracking-tight text-sobre-jade">
                                    Aprende<span className="text-oro">Mam</span>
                                </p>
                                <p className="truncate text-xs text-jade-claro">
                                    {sesion.nombre} · {sesion.rol_nombre}
                                </p>
                            </div>
                        </div>

                        {/*
                            Se desplaza en horizontal en vez de partirse en dos: con las cinco
                            secciones de un administrador cabe entera a partir de 375 px.

                            Sin degradado en el borde a propósito: como casi siempre cabe, un
                            degradado fijo se lee como si «Mi cuenta» estuviera deshabilitado.

                            `self-end` es lo que sostiene el filo de la pestaña activa contra
                            el canto de la banda cuando la fila es una sola: sin él quedaría a
                            media altura y dejaría de leerse como una pestaña. El filete separa
                            identidad de navegación y sobra cuando ya no hay dos filas.
                        */}
                        <nav
                            aria-label="Secciones del panel"
                            className="order-1 w-full self-end overflow-x-auto border-t border-sobre-jade/10 [scrollbar-width:none] una-fila:order-0 una-fila:w-auto una-fila:shrink-0 una-fila:border-0 [&::-webkit-scrollbar]:hidden"
                        >
                            <ul className="flex min-w-max gap-x-5">
                                <li>
                                    <NavLink to="/entradas" className={claseDeEnlace}>
                                        Entradas
                                    </NavLink>
                                </li>

                                {/* La API los llama «categorías»; el panel, la exportación y
                                    el sitio público los llaman «temas». Gana la palabra que
                                    lee el docente. */}
                                <li>
                                    <NavLink to="/categorias" className={claseDeEnlace}>
                                        Temas
                                    </NavLink>
                                </li>

                                <li>
                                    <NavLink to="/fuentes" className={claseDeEnlace}>
                                        Fuentes
                                    </NavLink>
                                </li>

                                <li>
                                    <NavLink to="/paginas" className={claseDeEnlace}>
                                        Páginas
                                    </NavLink>
                                </li>

                                <li>
                                    <NavLink to="/documentos" className={claseDeEnlace}>
                                        Documentos
                                    </NavLink>
                                </li>

                                {sesion.es_administrador && (
                                    <li>
                                        <NavLink to="/usuarios" className={claseDeEnlace}>
                                            Cuentas
                                        </NavLink>
                                    </li>
                                )}

                                <li>
                                    <NavLink to="/mi-cuenta" className={claseDeEnlace}>
                                        Mi cuenta
                                    </NavLink>
                                </li>
                            </ul>
                        </nav>

                        <form
                            method="POST"
                            action="/admin/logout"
                            className="ml-auto shrink-0"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value={tokenDelCascaron()}
                            />
                            <button type="submit" className="boton-banda">
                                Salir
                            </button>
                        </form>
                    </div>
                </div>

                <div className="cenefa" aria-hidden="true" />

                <EstadoDePublicacion />
            </header>

            <main
                id="contenido"
                tabIndex={-1}
                className="mx-auto max-w-6xl px-4 py-6 focus:outline-none sm:px-6 sm:py-8"
            >
                <Outlet />
            </main>
        </div>
    );
}
