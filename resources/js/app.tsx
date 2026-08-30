/**
 * Punto de montaje del panel.
 *
 * El enrutado es del lado del cliente, con `basename="/admin"`, y lo sostiene la ruta
 * comodín `/admin/{ruta?}` de `routes/web.php`: sin ella, recargar en `/admin/entradas/12`
 * daría un 404 del servidor. Las dos piezas van juntas; si un día se toca una, hay que
 * mirar la otra.
 */

import type { ReactNode } from "react";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter, Link, Navigate, Route, Routes } from "react-router";

import { Layout } from "./componentes/Layout";
import { ProveedorPanel, usePanel } from "./panel";
import { FormularioCategoria } from "./paginas/FormularioCategoria";
import { FormularioEntrada } from "./paginas/FormularioEntrada";
import { FormularioFuente } from "./paginas/FormularioFuente";
import { FormularioUsuario } from "./paginas/FormularioUsuario";
import { ListaCategorias } from "./paginas/ListaCategorias";
import { ListaEntradas } from "./paginas/ListaEntradas";
import { ListaFuentes } from "./paginas/ListaFuentes";
import { ListaUsuarios } from "./paginas/ListaUsuarios";
import { MiCuenta } from "./paginas/MiCuenta";

/**
 * La gestión de cuentas es solo de administradores.
 *
 * Esto no es el candado —el candado es `UserPolicy` en el servidor, y sigue puesto aunque
 * alguien escriba la URL a mano—: es no dejar que un editor llegue a una pantalla que solo
 * le puede responder 403.
 */
function SoloAdministradores({ children }: { children: ReactNode }) {
    const { sesion } = usePanel();

    if (!sesion.es_administrador) {
        return (
            <div className="py-10 text-center">
                <p className="text-sm text-tinta-suave">
                    Solo un administrador puede gestionar las cuentas del panel.
                </p>
                <Link
                    to="/entradas"
                    className="mt-2 inline-block text-sm underline-offset-2 hover:underline"
                >
                    Ir a las entradas
                </Link>
            </div>
        );
    }

    return children;
}

function NoEncontrado() {
    return (
        <div className="py-10 text-center">
            <p className="text-sm text-tinta-suave">Esa pantalla del panel no existe.</p>
            <Link
                to="/entradas"
                className="mt-2 inline-block text-sm underline-offset-2 hover:underline"
            >
                Ir a las entradas
            </Link>
        </div>
    );
}

function Panel() {
    return (
        <BrowserRouter basename="/admin">
            <ProveedorPanel>
                <Routes>
                    <Route element={<Layout />}>
                        <Route index element={<Navigate to="/entradas" replace />} />

                        <Route path="entradas" element={<ListaEntradas />} />
                        <Route path="entradas/nueva" element={<FormularioEntrada />} />
                        <Route path="entradas/:id" element={<FormularioEntrada />} />

                        <Route path="categorias" element={<ListaCategorias />} />
                        <Route
                            path="categorias/nuevo"
                            element={<FormularioCategoria />}
                        />
                        <Route path="categorias/:id" element={<FormularioCategoria />} />

                        <Route path="fuentes" element={<ListaFuentes />} />
                        <Route path="fuentes/nueva" element={<FormularioFuente />} />
                        <Route path="fuentes/:id" element={<FormularioFuente />} />

                        <Route
                            path="usuarios"
                            element={
                                <SoloAdministradores>
                                    <ListaUsuarios />
                                </SoloAdministradores>
                            }
                        />
                        <Route
                            path="usuarios/nueva"
                            element={
                                <SoloAdministradores>
                                    <FormularioUsuario />
                                </SoloAdministradores>
                            }
                        />
                        <Route
                            path="usuarios/:id"
                            element={
                                <SoloAdministradores>
                                    <FormularioUsuario />
                                </SoloAdministradores>
                            }
                        />

                        <Route path="mi-cuenta" element={<MiCuenta />} />

                        <Route path="*" element={<NoEncontrado />} />
                    </Route>
                </Routes>
            </ProveedorPanel>
        </BrowserRouter>
    );
}

const contenedor = document.getElementById("panel");

if (contenedor !== null) {
    createRoot(contenedor).render(
        <StrictMode>
            <Panel />
        </StrictMode>,
    );
}
