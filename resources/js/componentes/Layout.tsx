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
        "inline-block border-b-[3px] py-2.5 text-sm whitespace-nowrap",
        isActive
            ? "border-sobre-jade font-semibold text-sobre-jade"
            : "border-transparent text-jade-claro hover:text-sobre-jade",
    ].join(" ");
}

export function Layout() {
    const { sesion } = usePanel();

    return (
        <div className="min-h-screen bg-papel text-tinta">
            <header>
                <div className="bg-jade-hondo">
                    <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
                        {/*
                            `alt=""`: el nombre del sitio va escrito al lado, dentro del mismo
                            bloque. Con texto alternativo, un lector de pantalla anunciaría
                            «AprendeMam AprendeMam».

                            `rounded-full` no es adorno: el WebP es el logo circular sobre un
                            cuadrado blanco, y sin el recorte se ven cuatro esquinas blancas
                            sobre el verde.
                        */}
                        <img
                            src="/logo.webp"
                            alt=""
                            width="36"
                            height="36"
                            className="size-9 shrink-0 rounded-full"
                        />

                        <div className="min-w-0">
                            <p className="text-xl leading-tight font-bold text-sobre-jade">
                                Aprende<span className="text-oro">Mam</span>
                            </p>
                            <p className="truncate text-xs text-jade-claro">
                                {sesion.nombre} · {sesion.rol_nombre}
                            </p>
                        </div>

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

                    {/*
                        La fila de secciones se desplaza en horizontal en vez de partirse en
                        dos: una segunda fila empujaría el contenido hacia abajo justo en la
                        pantalla donde menos alto sobra. Con las cinco secciones de un
                        administrador cabe entera a partir de 375 px y solo se desplaza por
                        debajo de eso.

                        Sin degradado en el borde a propósito: como casi siempre cabe, un
                        degradado fijo se lee como si «Mi cuenta» estuviera deshabilitado.
                        Cuando de verdad no cabe, la palabra cortada ya avisa de que hay más.
                    */}
                    <div>
                        <nav
                            aria-label="Secciones del panel"
                            className="mx-auto max-w-6xl overflow-x-auto px-4 [scrollbar-width:none] sm:px-6 [&::-webkit-scrollbar]:hidden"
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
                    </div>
                </div>

                <div className="cenefa" aria-hidden="true" />

                <EstadoDePublicacion />
            </header>

            <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
                <Outlet />
            </main>
        </div>
    );
}
