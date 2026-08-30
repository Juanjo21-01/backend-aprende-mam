/**
 * Alta y edición de una fuente bibliográfica.
 *
 * Nada de este formulario pasa por el normalizador del Mam, y no es un olvido: un título es
 * una **cita** y tiene que reproducir la portada tal como está impresa, incluso si la
 * portada escribe el Mam de otra forma. Corregirlo aquí falsearía la referencia.
 */

import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";

import { ErrorApi } from "../api/cliente";
import { actualizarFuente, crearFuente, obtenerFuente } from "../api/recursos";
import { Aviso, Cargando } from "../componentes/Estados";
import { usePanel } from "../panel";
import type { DatosFuente, Fuente } from "../tipos";

interface Formulario {
    titulo: string;
    institucion: string;
    anio: string;
    licencia: string;
    url: string;
}

const VACIO: Formulario = {
    titulo: "",
    institucion: "",
    anio: "",
    licencia: "",
    url: "",
};

function desdeFuente(fuente: Fuente): Formulario {
    return {
        titulo: fuente.titulo,
        institucion: fuente.institucion ?? "",
        anio: fuente.anio?.toString() ?? "",
        licencia: fuente.licencia ?? "",
        url: fuente.url ?? "",
    };
}

function aDatos(formulario: Formulario): DatosFuente {
    const texto = (valor: string): string | null =>
        valor.trim() === "" ? null : valor;

    return {
        titulo: formulario.titulo,
        institucion: texto(formulario.institucion),
        anio: formulario.anio === "" ? null : Number(formulario.anio),
        licencia: texto(formulario.licencia),
        url: texto(formulario.url),
    };
}

export function FormularioFuente() {
    const { id } = useParams();
    const navegar = useNavigate();
    const { recargarCatalogos } = usePanel();

    const esAlta = id === undefined;

    const [formulario, setFormulario] = useState<Formulario>(VACIO);
    const [cargando, setCargando] = useState(!esAlta);
    const [guardando, setGuardando] = useState(false);
    const [fallo, setFallo] = useState<ErrorApi | Error | null>(null);

    useEffect(() => {
        if (esAlta) {
            setFormulario(VACIO);
            setCargando(false);

            return;
        }

        let cancelado = false;
        setCargando(true);

        obtenerFuente(Number(id))
            .then((fuente) => {
                if (!cancelado) {
                    setFormulario(desdeFuente(fuente));
                    setFallo(null);
                }
            })
            .catch((error: unknown) => {
                if (!cancelado) {
                    setFallo(
                        error instanceof Error
                            ? error
                            : new Error("No se pudo cargar la fuente."),
                    );
                }
            })
            .finally(() => {
                if (!cancelado) {
                    setCargando(false);
                }
            });

        return () => {
            cancelado = true;
        };
    }, [id, esAlta]);

    function cambiar<C extends keyof Formulario>(
        campo: C,
        valor: Formulario[C],
    ): void {
        setFormulario((previo) => ({ ...previo, [campo]: valor }));
    }

    async function guardar(): Promise<void> {
        setGuardando(true);
        setFallo(null);

        try {
            const datos = aDatos(formulario);

            if (esAlta) {
                await crearFuente(datos);
            } else {
                await actualizarFuente(Number(id), datos);
            }

            // El selector de fuentes del formulario de entradas usa esta lista.
            await recargarCatalogos();
            navegar("/fuentes");
        } catch (error) {
            setFallo(
                error instanceof Error ? error : new Error("No se pudo guardar."),
            );
        } finally {
            setGuardando(false);
        }
    }

    const errorDe = (campo: string): string | undefined =>
        fallo instanceof ErrorApi ? fallo.campo(campo) : undefined;

    const errorGeneral =
        fallo === null
            ? null
            : fallo instanceof ErrorApi && fallo.estado === 422
              ? null
              : fallo.message;

    if (cargando) {
        return <Cargando texto="Cargando la fuente…" />;
    }

    return (
        <div className="grid max-w-2xl gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold">
                    {esAlta ? "Nueva fuente" : "Editar fuente"}
                </h1>

                <Link
                    to="/fuentes"
                    className="text-sm text-tinta-suave underline-offset-2 hover:underline"
                >
                    Volver a las fuentes
                </Link>
            </div>

            {errorGeneral !== null && <Aviso>{errorGeneral}</Aviso>}

            <form
                className="tarjeta grid gap-4 p-5"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    void guardar();
                }}
            >
                <div>
                    <label className="etiqueta" htmlFor="titulo">
                        Título
                    </label>
                    <input
                        id="titulo"
                        className="campo"
                        value={formulario.titulo}
                        onChange={(evento) => cambiar("titulo", evento.target.value)}
                        required
                        autoFocus
                        autoComplete="off"
                    />
                    <p className="mt-1 text-xs text-tinta-suave">
                        Como está impreso en la portada. Es una cita: no se corrige la
                        ortografía, ni siquiera la del Mam.
                    </p>
                    {errorDe("titulo") !== undefined && (
                        <p className="mt-1 text-xs text-alerta">{errorDe("titulo")}</p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                    <div>
                        <label className="etiqueta" htmlFor="institucion">
                            Institución{" "}
                            <span className="font-normal text-tinta-suave">(opcional)</span>
                        </label>
                        <input
                            id="institucion"
                            className="campo"
                            value={formulario.institucion}
                            onChange={(evento) =>
                                cambiar("institucion", evento.target.value)
                            }
                            placeholder="MINEDUC, DIGEBI, ALMG"
                            autoComplete="off"
                        />
                        {errorDe("institucion") !== undefined && (
                            <p className="mt-1 text-xs text-alerta">
                                {errorDe("institucion")}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="anio">
                            Año{" "}
                            <span className="font-normal text-tinta-suave">(opcional)</span>
                        </label>
                        <input
                            id="anio"
                            className="campo"
                            type="number"
                            min={1900}
                            value={formulario.anio}
                            onChange={(evento) => cambiar("anio", evento.target.value)}
                        />
                        {errorDe("anio") !== undefined && (
                            <p className="mt-1 text-xs text-alerta">{errorDe("anio")}</p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="etiqueta" htmlFor="licencia">
                        Licencia{" "}
                        <span className="font-normal text-tinta-suave">(opcional)</span>
                    </label>
                    <input
                        id="licencia"
                        className="campo"
                        value={formulario.licencia}
                        onChange={(evento) => cambiar("licencia", evento.target.value)}
                        placeholder="Uso educativo, CC BY-SA 4.0"
                        autoComplete="off"
                    />
                    {errorDe("licencia") !== undefined && (
                        <p className="mt-1 text-xs text-alerta">{errorDe("licencia")}</p>
                    )}
                </div>

                <div>
                    <label className="etiqueta" htmlFor="url">
                        Dirección web{" "}
                        <span className="font-normal text-tinta-suave">(opcional)</span>
                    </label>
                    <input
                        id="url"
                        className="campo"
                        type="url"
                        value={formulario.url}
                        onChange={(evento) => cambiar("url", evento.target.value)}
                        placeholder="https://"
                        autoComplete="off"
                    />
                    {errorDe("url") !== undefined && (
                        <p className="mt-1 text-xs text-alerta">{errorDe("url")}</p>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-3 border-t border-borde pt-4">
                    <button type="submit" className="boton" disabled={guardando}>
                        {guardando ? "Guardando…" : "Guardar"}
                    </button>

                    <Link
                        to="/fuentes"
                        className="text-sm text-tinta-suave underline-offset-2 hover:underline"
                    >
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    );
}
