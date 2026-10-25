/**
 * Páginas editoriales: cultura, gramática y base legal.
 *
 * Es el contenido que el docente escribe sin programador. Como las entradas, nace sin revisar
 * y el sitio público solo muestra lo que el validador lingüístico firmó; por eso cada fila
 * dice si ya se publica. Se agrupa por sección porque así se navega el sitio.
 */

import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router";

import { borrarPagina, listarPaginas } from "../api/recursos";
import { Confirmacion } from "../componentes/Confirmacion";
import { Aviso, Cargando, Vacio } from "../componentes/Estados";
import {
    IconoActualizar,
    IconoBorrar,
    IconoEditar,
    IconoNueva,
} from "../componentes/Iconos";
import { usePanel } from "../panel";
import { SECCIONES } from "../tipos";
import type { PaginaEditorial } from "../tipos";

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

export function ListaPaginas() {
    const { sesion } = usePanel();

    const [paginas, setPaginas] = useState<PaginaEditorial[] | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [ocupada, setOcupada] = useState<number | null>(null);
    const [aBorrar, setABorrar] = useState<PaginaEditorial | null>(null);

    const cargar = useCallback(async (): Promise<void> => {
        setCargando(true);

        try {
            setPaginas(await listarPaginas());
            setError(null);
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => {
        void cargar();
    }, [cargar]);

    async function borrar(pagina: PaginaEditorial): Promise<void> {
        setABorrar(null);
        setOcupada(pagina.id);
        setError(null);

        try {
            await borrarPagina(pagina.id);
            await cargar();
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setOcupada(null);
        }
    }

    return (
        <div className="grid gap-5">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Páginas</h1>
                    <p className="mt-1 text-sm text-tinta-suave">
                        Cultura, gramática y base legal. Solo lo revisado llega al sitio
                        público.
                    </p>
                </div>

                <Link to="/paginas/nueva" className="boton">
                    <IconoNueva />
                    Nueva página
                </Link>
            </div>

            {error !== null && (
                <Aviso
                    accion={
                        <button
                            type="button"
                            className="boton-secundario"
                            onClick={() => void cargar()}
                        >
                            <IconoActualizar />
                            Reintentar
                        </button>
                    }
                >
                    {error}
                </Aviso>
            )}

            {cargando && paginas === null ? (
                <div className="tarjeta">
                    <Cargando />
                </div>
            ) : paginas === null || paginas.length === 0 ? (
                <div className="tarjeta">
                    <Vacio
                        titulo="Todavía no hay páginas"
                        accion={
                            <Link to="/paginas/nueva" className="boton">
                                <IconoNueva />
                                Escribir la primera
                            </Link>
                        }
                    >
                        Las secciones de cultura, gramática y base legal del sitio salen de
                        aquí.
                    </Vacio>
                </div>
            ) : (
                SECCIONES.map(({ valor, nombre }) => {
                    const deLaSeccion = paginas.filter((p) => p.seccion === valor);

                    return (
                        <section key={valor} className="grid gap-2">
                            <h2 className="text-sm font-semibold text-tinta-suave">
                                {nombre}
                            </h2>

                            <div className="tarjeta">
                                {deLaSeccion.length === 0 ? (
                                    <p className="p-4 text-sm text-tinta-suave">
                                        Sin páginas en esta sección.
                                    </p>
                                ) : (
                                    <ul className="lista-fichas">
                                        {deLaSeccion.map((pagina) => (
                                            <li key={pagina.id} className="ficha">
                                                <div className="flex items-start justify-between gap-3">
                                                    <Link
                                                        to={`/paginas/${pagina.id}`}
                                                        className="enlace font-medium"
                                                    >
                                                        {pagina.titulo}
                                                    </Link>

                                                    {/* El color no es el canal: la frase
                                                        dice si se publica o no. */}
                                                    <span className="flex shrink-0 items-center gap-2 text-xs text-tinta-suave">
                                                        <span
                                                            className={
                                                                pagina.revisado
                                                                    ? "marca-revision"
                                                                    : "marca-revision-hueca"
                                                            }
                                                            aria-hidden="true"
                                                        />
                                                        {pagina.revisado
                                                            ? "Revisada · se publica"
                                                            : "Sin revisar"}
                                                    </span>
                                                </div>

                                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-tinta-suave">
                                                    <span className="font-mono">
                                                        /{pagina.slug}
                                                    </span>
                                                    <span>Orden {pagina.orden}</span>
                                                    {pagina.video_url !== null && (
                                                        <span>Con video</span>
                                                    )}
                                                </div>

                                                <div className="acciones-fila border-t border-borde pt-2 whitespace-nowrap">
                                                    <Link
                                                        to={`/paginas/${pagina.id}`}
                                                        className="boton-fila"
                                                    >
                                                        <IconoEditar className="size-3.5" />
                                                        Editar
                                                    </Link>

                                                    {sesion.es_administrador && (
                                                        <button
                                                            type="button"
                                                            className="boton-fila-peligro"
                                                            disabled={ocupada === pagina.id}
                                                            onClick={() => setABorrar(pagina)}
                                                        >
                                                            <IconoBorrar className="size-3.5" />
                                                            Borrar
                                                        </button>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </section>
                    );
                })
            )}

            {aBorrar !== null && (
                <Confirmacion
                    titulo={`¿Borrar «${aBorrar.titulo}»?`}
                    textoConfirmar="Borrar la página"
                    peligro
                    ocupado={ocupada === aBorrar.id}
                    confirmar={() => void borrar(aBorrar)}
                    cancelar={() => setABorrar(null)}
                >
                    <p>
                        {aBorrar.revisado
                            ? "Está revisada, así que dejará de estar en el sitio público en la próxima publicación."
                            : "Todavía no estaba en el sitio público."}{" "}
                        No se puede deshacer.
                    </p>
                </Confirmacion>
            )}
        </div>
    );
}
