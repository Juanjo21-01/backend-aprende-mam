/**
 * Fuentes bibliográficas.
 *
 * No son un catálogo administrativo: son el requisito académico del proyecto. Cada entrada
 * conserva de dónde salió, y el sitio público cita la procedencia al pie. Por eso borrar una
 * fuente que ya tiene entradas se avisa con su número delante.
 */

import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router";

import { borrarFuente, listarFuentes } from "../api/recursos";
import { Confirmacion } from "../componentes/Confirmacion";
import { Aviso, Cargando, Vacio } from "../componentes/Estados";
import {
    IconoAbrir,
    IconoActualizar,
    IconoBorrar,
    IconoEditar,
    IconoNueva,
} from "../componentes/Iconos";
import { usePanel } from "../panel";
import { ANCHO_TABLA, useCabeLaTabla } from "../pantalla";
import type { Fuente } from "../tipos";

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

export function ListaFuentes() {
    const { sesion, recargarCatalogos } = usePanel();
    const cabeLaTabla = useCabeLaTabla(ANCHO_TABLA.fuentes);

    const [fuentes, setFuentes] = useState<Fuente[] | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [ocupada, setOcupada] = useState<number | null>(null);
    const [aBorrar, setABorrar] = useState<Fuente | null>(null);

    const cargar = useCallback(async (): Promise<void> => {
        setCargando(true);

        try {
            setFuentes(await listarFuentes());
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

    async function borrar(fuente: Fuente): Promise<void> {
        setABorrar(null);
        setOcupada(fuente.id);
        setError(null);

        try {
            await borrarFuente(fuente.id);
            await cargar();
            // El selector de fuentes del formulario de entradas usa esta lista.
            await recargarCatalogos();
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setOcupada(null);
        }
    }

    function Acciones({ fuente }: { fuente: Fuente }) {
        return (
            <div className="acciones-fila">
                <Link to={`/fuentes/${fuente.id}`} className="boton-fila">
                    <IconoEditar className="size-3.5" />
                    Editar
                </Link>

                {sesion.es_administrador && (
                    <button
                        type="button"
                        className="boton-fila-peligro"
                        disabled={ocupada === fuente.id}
                        onClick={() => setABorrar(fuente)}
                    >
                        <IconoBorrar className="size-3.5" />
                        Borrar
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className="grid gap-5">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Fuentes</h1>
                    <p className="mt-1 text-sm text-tinta-suave">
                        De dónde salió cada palabra. El sitio público las cita al pie de la
                        entrada.
                    </p>
                </div>

                <Link to="/fuentes/nueva" className="boton">
                    <IconoNueva />
                    Nueva fuente
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

            <div className="tarjeta overflow-x-auto">
                {cargando && fuentes === null ? (
                    <Cargando />
                ) : fuentes === null || fuentes.length === 0 ? (
                    <Vacio
                        titulo="Todavía no hay fuentes"
                        accion={
                            <Link to="/fuentes/nueva" className="boton">
                                <IconoNueva />
                                Registrar la primera
                            </Link>
                        }
                    >
                        Sin ellas las entradas quedan sin procedencia, y la trazabilidad
                        bibliográfica es un requisito del proyecto.
                    </Vacio>
                ) : cabeLaTabla ? (
                    <table className="tabla">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Institución</th>
                                <th>Año</th>
                                <th>Licencia</th>
                                <th>Entradas</th>
                                <th className="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {fuentes.map((fuente) => (
                                <tr key={fuente.id}>
                                    <td>
                                        <Link
                                            to={`/fuentes/${fuente.id}`}
                                            className="enlace font-medium"
                                        >
                                            {fuente.titulo}
                                        </Link>

                                        {fuente.url !== null && (
                                            <a
                                                href={fuente.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="enlace-accion ml-2 text-tinta-suave"
                                            >
                                                abrir
                                                <IconoAbrir className="size-3" />
                                            </a>
                                        )}
                                    </td>
                                    <td>{fuente.institucion ?? "—"}</td>

                                    {/* El título envuelve a propósito —para eso es un
                                        título— y estas tres no: un año partido en dos
                                        renglones no es un año. Fuentes es la única tabla que
                                        no cabe entera ni a 1152 px. */}
                                    <td className="text-tinta-suave whitespace-nowrap">
                                        {fuente.anio ?? "—"}
                                    </td>
                                    <td className="text-tinta-suave whitespace-nowrap">
                                        {fuente.licencia ?? "—"}
                                    </td>
                                    <td className="text-tinta-suave whitespace-nowrap">
                                        {fuente.total_entradas ?? 0}
                                    </td>
                                    <td className="text-right whitespace-nowrap">
                                        <Acciones fuente={fuente} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <ul className="lista-fichas">
                        {fuentes.map((fuente) => (
                            <li key={fuente.id} className="ficha">
                                <div className="flex items-start justify-between gap-3">
                                    <Link
                                        to={`/fuentes/${fuente.id}`}
                                        className="enlace font-medium"
                                    >
                                        {fuente.titulo}
                                    </Link>

                                    <span className="shrink-0 text-xs text-tinta-suave">
                                        {fuente.total_entradas ?? 0} entrada(s)
                                    </span>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-tinta-suave">
                                    {fuente.institucion !== null && (
                                        <span>{fuente.institucion}</span>
                                    )}
                                    {fuente.anio !== null && <span>{fuente.anio}</span>}
                                    {fuente.licencia !== null && (
                                        <span>{fuente.licencia}</span>
                                    )}
                                    {fuente.url !== null && (
                                        <a
                                            href={fuente.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="enlace inline-flex items-center gap-1"
                                        >
                                            abrir
                                            <IconoAbrir className="size-3" />
                                        </a>
                                    )}
                                </div>

                                <div className="border-t border-borde pt-2 whitespace-nowrap">
                                    <Acciones fuente={fuente} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {aBorrar !== null && (
                <Confirmacion
                    titulo={`¿Borrar «${aBorrar.titulo}»?`}
                    textoConfirmar="Borrar la fuente"
                    peligro
                    ocupado={ocupada === aBorrar.id}
                    confirmar={() => void borrar(aBorrar)}
                    cancelar={() => setABorrar(null)}
                >
                    {(aBorrar.total_entradas ?? 0) > 0 ? (
                        <>
                            <p>
                                <strong className="font-semibold text-tinta">
                                    {aBorrar.total_entradas} entrada(s) la citan
                                </strong>{" "}
                                y se quedan sin procedencia.
                            </p>
                            <p>
                                La trazabilidad bibliográfica es un requisito del proyecto, así
                                que esto no es un borrado cualquiera.
                            </p>
                        </>
                    ) : (
                        <p>Ninguna entrada la cita todavía. Aun así, no se puede deshacer.</p>
                    )}
                </Confirmacion>
            )}
        </div>
    );
}
