/**
 * Encabezado, navegación y contenedor de las pantallas.
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
        "border-b-2 px-1 pb-2 text-sm font-medium",
        isActive ? "border-acento text-texto" : "border-transparent text-tenue",
    ].join(" ");
}

export function Layout() {
    const { sesion } = usePanel();

    return (
        <div className="min-h-screen bg-fondo text-texto">
            <header className="border-b border-borde bg-tarjeta">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-6 pt-4">
                    <div>
                        <p className="font-semibold">AprendeMam</p>
                        <p className="text-sm text-tenue">
                            {sesion.nombre} · {sesion.rol_nombre}
                        </p>
                    </div>

                    <EstadoDePublicacion />

                    <form method="POST" action="/admin/logout">
                        <input
                            type="hidden"
                            name="_token"
                            value={tokenDelCascaron()}
                        />
                        <button type="submit" className="boton-secundario">
                            Salir
                        </button>
                    </form>
                </div>

                <nav className="mx-auto flex max-w-6xl flex-wrap gap-6 px-6 pt-4">
                    <NavLink to="/entradas" className={claseDeEnlace}>
                        Entradas
                    </NavLink>

                    {/* La API los llama «categorías»; el panel, la exportación y el sitio
                        público los llaman «temas». Gana la palabra que lee el docente. */}
                    <NavLink to="/categorias" className={claseDeEnlace}>
                        Temas
                    </NavLink>

                    <NavLink to="/fuentes" className={claseDeEnlace}>
                        Fuentes
                    </NavLink>

                    {sesion.es_administrador && (
                        <NavLink to="/usuarios" className={claseDeEnlace}>
                            Cuentas
                        </NavLink>
                    )}

                    <NavLink to="/mi-cuenta" className={claseDeEnlace}>
                        Mi cuenta
                    </NavLink>
                </nav>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-8">
                <Outlet />
            </main>
        </div>
    );
}
