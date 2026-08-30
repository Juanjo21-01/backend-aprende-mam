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
import { Aviso, Cargando, Vacio } from "../componentes/Estados";
import { usePanel } from "../panel";
import type { Fuente } from "../tipos";

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

export function ListaFuentes() {
    const { sesion, recargarCatalogos } = usePanel();

    const [fuentes, setFuentes] = useState<Fuente[] | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [ocupada, setOcupada] = useState<number | null>(null);

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
        const cuantas = fuente.total_entradas ?? 0;

        const aviso =
            cuantas > 0
                ? `¿Borrar «${fuente.titulo}»?\n\n` +
                  `${cuantas} entrada(s) la citan y se quedan sin procedencia. La ` +
                  `trazabilidad bibliográfica es un requisito del proyecto, así que esto no ` +
                  `es un borrado cualquiera.`
                : `¿Borrar «${fuente.titulo}»? Esto no se puede deshacer.`;

        if (!window.confirm(aviso)) {
            return;
        }

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
                    <Vacio>
                        Todavía no hay fuentes. Sin ellas, las entradas quedan sin
                        procedencia.
                    </Vacio>
                ) : (
                    <table className="tabla">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Institución</th>
                                <th>Año</th>
                                <th>Licencia</th>
                                <th>Entradas</th>
                                <th className="text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {fuentes.map((fuente) => (
                                <tr
                                    key={fuente.id}
                                    className="border-b border-borde last:border-0"
                                >
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
                                                className="enlace ml-2 text-xs text-tinta-suave"
                                            >
                                                abrir
                                            </a>
                                        )}
                                    </td>
                                    <td>
                                        {fuente.institucion ?? "—"}
                                    </td>
                                    <td className="text-tinta-suave">
                                        {fuente.anio ?? "—"}
                                    </td>
                                    <td className="text-tinta-suave">
                                        {fuente.licencia ?? "—"}
                                    </td>
                                    <td className="text-tinta-suave">
                                        {fuente.total_entradas ?? 0}
                                    </td>
                                    <td className="text-right whitespace-nowrap">
                                        <Link
                                            to={`/fuentes/${fuente.id}`}
                                            className="enlace text-xs"
                                        >
                                            Editar
                                        </Link>

                                        {sesion.es_administrador && (
                                            <button
                                                type="button"
                                                className="enlace-peligro ml-3"
                                                disabled={ocupada === fuente.id}
                                                onClick={() => void borrar(fuente)}
                                            >
                                                Borrar
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
