/**
 * Materiales descargables: guías, fichas, cuadernillos e infografías.
 *
 * Aquí solo se administran los metadatos. El PDF vive en el sitio o en un CDN y se baja bajo
 * demanda; el panel no lo aloja. Solo lo revisado llega al sitio público.
 */

import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router";

import { borrarDocumento, listarDocumentos } from "../api/recursos";
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
import type { Documento } from "../tipos";

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

export function ListaDocumentos() {
    const { sesion } = usePanel();

    const [documentos, setDocumentos] = useState<Documento[] | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [ocupado, setOcupado] = useState<number | null>(null);
    const [aBorrar, setABorrar] = useState<Documento | null>(null);

    const cargar = useCallback(async (): Promise<void> => {
        setCargando(true);

        try {
            setDocumentos(await listarDocumentos());
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

    async function borrar(documento: Documento): Promise<void> {
        setABorrar(null);
        setOcupado(documento.id);
        setError(null);

        try {
            await borrarDocumento(documento.id);
            await cargar();
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setOcupado(null);
        }
    }

    return (
        <div className="grid gap-5">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Documentos</h1>
                    <p className="mt-1 text-sm text-tinta-suave">
                        PDF descargables para el aula. El archivo no se sube aquí: se
                        registra su dirección.
                    </p>
                </div>

                <Link to="/documentos/nuevo" className="boton">
                    <IconoNueva />
                    Nuevo documento
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

            <div className="tarjeta">
                {cargando && documentos === null ? (
                    <Cargando />
                ) : documentos === null || documentos.length === 0 ? (
                    <Vacio
                        titulo="Todavía no hay documentos"
                        accion={
                            <Link to="/documentos/nuevo" className="boton">
                                <IconoNueva />
                                Registrar el primero
                            </Link>
                        }
                    >
                        La sección de materiales del sitio sale de aquí.
                    </Vacio>
                ) : (
                    <ul className="lista-fichas">
                        {documentos.map((documento) => (
                            <li key={documento.id} className="ficha">
                                <div className="flex items-start justify-between gap-3">
                                    <Link
                                        to={`/documentos/${documento.id}`}
                                        className="enlace font-medium"
                                    >
                                        {documento.titulo}
                                    </Link>

                                    <span className="flex shrink-0 items-center gap-2 text-xs text-tinta-suave">
                                        <span
                                            className={
                                                documento.revisado
                                                    ? "marca-revision"
                                                    : "marca-revision-hueca"
                                            }
                                            aria-hidden="true"
                                        />
                                        {documento.revisado
                                            ? "Revisado · se publica"
                                            : "Sin revisar"}
                                    </span>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-tinta-suave">
                                    <span>{documento.tipo_nombre}</span>
                                    {documento.tamano_kb !== null && (
                                        <span>{documento.tamano_kb} KB</span>
                                    )}
                                    {documento.licencia !== null && (
                                        <span>{documento.licencia}</span>
                                    )}
                                    <a
                                        href={documento.archivo_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="enlace inline-flex items-center gap-1"
                                    >
                                        abrir PDF
                                        <IconoAbrir className="size-3" />
                                    </a>
                                </div>

                                <div className="acciones-fila border-t border-borde pt-2 whitespace-nowrap">
                                    <Link
                                        to={`/documentos/${documento.id}`}
                                        className="boton-fila"
                                    >
                                        <IconoEditar className="size-3.5" />
                                        Editar
                                    </Link>

                                    {sesion.es_administrador && (
                                        <button
                                            type="button"
                                            className="boton-fila-peligro"
                                            disabled={ocupado === documento.id}
                                            onClick={() => setABorrar(documento)}
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

            {aBorrar !== null && (
                <Confirmacion
                    titulo={`¿Borrar «${aBorrar.titulo}»?`}
                    textoConfirmar="Borrar el documento"
                    peligro
                    ocupado={ocupado === aBorrar.id}
                    confirmar={() => void borrar(aBorrar)}
                    cancelar={() => setABorrar(null)}
                >
                    <p>
                        Se borra el registro, no el archivo PDF, que sigue donde esté
                        colocado. No se puede deshacer.
                    </p>
                </Confirmacion>
            )}
        </div>
    );
}
