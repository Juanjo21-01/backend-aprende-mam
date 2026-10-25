/**
 * Alta y edición de una página editorial.
 *
 * El título y el cuerpo mezclan castellano con Mam, así que el servidor los pasa por el
 * normalizador al guardar, igual que el lema de una entrada; por eso el cuerpo lleva el
 * teclado de letras del Mam. El cuerpo es Markdown simple: sin HTML, y con imágenes solo por
 * dirección (https o ruta del sitio), porque no se suben archivos.
 */

import { useEffect, useRef, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";

import { ErrorApi } from "../api/cliente";
import {
    actualizarPagina,
    crearPagina,
    marcarRevisionPagina,
    obtenerPagina,
} from "../api/recursos";
import { Aviso, Cargando } from "../componentes/Estados";
import { IconoActivar, IconoDesactivar, IconoVolver } from "../componentes/Iconos";
import { TecladoDeGrafemas } from "../componentes/TecladoDeGrafemas";
import { usePanel } from "../panel";
import { SECCIONES } from "../tipos";
import type { DatosPagina, PaginaEditorial, SeccionPagina } from "../tipos";

interface Formulario {
    seccion: SeccionPagina;
    slug: string;
    titulo: string;
    cuerpo: string;
    orden: string;
    video_url: string;
    fuente_id: string;
}

const VACIO: Formulario = {
    seccion: "cultura",
    slug: "",
    titulo: "",
    cuerpo: "",
    orden: "0",
    video_url: "",
    fuente_id: "",
};

function desdePagina(pagina: PaginaEditorial): Formulario {
    return {
        seccion: pagina.seccion,
        slug: pagina.slug,
        titulo: pagina.titulo,
        cuerpo: pagina.cuerpo,
        orden: String(pagina.orden),
        video_url: pagina.video_url ?? "",
        fuente_id: pagina.fuente_id?.toString() ?? "",
    };
}

function aDatos(formulario: Formulario): DatosPagina {
    return {
        seccion: formulario.seccion,
        slug: formulario.slug,
        titulo: formulario.titulo,
        cuerpo: formulario.cuerpo,
        orden: formulario.orden === "" ? 0 : Number(formulario.orden),
        video_url: formulario.video_url.trim() === "" ? null : formulario.video_url,
        fuente_id: formulario.fuente_id === "" ? null : Number(formulario.fuente_id),
    };
}

export function FormularioPagina() {
    const { id } = useParams();
    const navegar = useNavigate();
    const { sesion, fuentes } = usePanel();
    const campoCuerpo = useRef<HTMLTextAreaElement>(null);

    const esAlta = id === undefined;

    const [formulario, setFormulario] = useState<Formulario>(VACIO);
    const [pagina, setPagina] = useState<PaginaEditorial | null>(null);
    const [cargando, setCargando] = useState(!esAlta);
    const [guardando, setGuardando] = useState(false);
    const [fallo, setFallo] = useState<ErrorApi | Error | null>(null);

    useEffect(() => {
        if (esAlta) {
            setFormulario(VACIO);
            setPagina(null);
            setCargando(false);

            return;
        }

        let cancelado = false;
        setCargando(true);

        obtenerPagina(Number(id))
            .then((cargada) => {
                if (!cancelado) {
                    setPagina(cargada);
                    setFormulario(desdePagina(cargada));
                    setFallo(null);
                }
            })
            .catch((error: unknown) => {
                if (!cancelado) {
                    setFallo(
                        error instanceof Error
                            ? error
                            : new Error("No se pudo cargar la página."),
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

    function cambiar<C extends keyof Formulario>(campo: C, valor: Formulario[C]): void {
        setFormulario((previo) => ({ ...previo, [campo]: valor }));
    }

    async function guardar(): Promise<void> {
        setGuardando(true);
        setFallo(null);

        try {
            const datos = aDatos(formulario);

            if (esAlta) {
                await crearPagina(datos);
            } else {
                await actualizarPagina(Number(id), datos);
            }

            navegar("/paginas");
        } catch (error) {
            setFallo(error instanceof Error ? error : new Error("No se pudo guardar."));
        } finally {
            setGuardando(false);
        }
    }

    async function alternarRevision(): Promise<void> {
        if (pagina === null) {
            return;
        }

        setGuardando(true);
        setFallo(null);

        try {
            setPagina(await marcarRevisionPagina(pagina.id, !pagina.revisado));
        } catch (error) {
            setFallo(
                error instanceof Error ? error : new Error("No se pudo cambiar la revisión."),
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
        return <Cargando texto="Cargando la página…" />;
    }

    return (
        <div className="grid max-w-3xl gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold">
                    {esAlta ? "Nueva página" : "Editar página"}
                </h1>

                <Link
                    to="/paginas"
                    className="enlace inline-flex items-center gap-1 text-sm text-tinta-suave"
                >
                    <IconoVolver className="size-3.5" />
                    Volver a las páginas
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
                <div className="grid gap-4 sm:grid-cols-[12rem_1fr]">
                    <div>
                        <label className="etiqueta" htmlFor="seccion">
                            Sección
                        </label>
                        <select
                            id="seccion"
                            className="campo"
                            value={formulario.seccion}
                            onChange={(evento) =>
                                cambiar("seccion", evento.target.value as SeccionPagina)
                            }
                        >
                            {SECCIONES.map(({ valor, nombre }) => (
                                <option key={valor} value={valor}>
                                    {nombre}
                                </option>
                            ))}
                        </select>
                        {errorDe("seccion") !== undefined && (
                            <p className="error-campo">{errorDe("seccion")}</p>
                        )}
                    </div>

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
                        {errorDe("titulo") !== undefined && (
                            <p className="error-campo">{errorDe("titulo")}</p>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                    <div>
                        <label className="etiqueta" htmlFor="slug">
                            Dirección
                        </label>
                        <input
                            id="slug"
                            className="campo font-mono"
                            value={formulario.slug}
                            onChange={(evento) => cambiar("slug", evento.target.value)}
                            placeholder="historia-del-mam"
                            required
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <p className="ayuda">
                            Va en la dirección web del sitio. Solo minúsculas, números y
                            guiones.
                        </p>
                        {errorDe("slug") !== undefined && (
                            <p className="error-campo">{errorDe("slug")}</p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="orden">
                            Orden
                        </label>
                        <input
                            id="orden"
                            className="campo"
                            type="number"
                            min={0}
                            value={formulario.orden}
                            onChange={(evento) => cambiar("orden", evento.target.value)}
                        />
                        {errorDe("orden") !== undefined && (
                            <p className="error-campo">{errorDe("orden")}</p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="etiqueta" htmlFor="cuerpo">
                        Texto
                    </label>
                    <textarea
                        id="cuerpo"
                        ref={campoCuerpo}
                        className="campo min-h-64"
                        lang="mam"
                        value={formulario.cuerpo}
                        onChange={(evento) => cambiar("cuerpo", evento.target.value)}
                        required
                        spellCheck={false}
                    />
                    <TecladoDeGrafemas
                        campo={campoCuerpo}
                        valor={formulario.cuerpo}
                        alCambiar={(nuevo) => cambiar("cuerpo", nuevo)}
                    />
                    <p className="ayuda">
                        Markdown simple: <code>## Título</code>, <code>**negrita**</code>,
                        listas con <code>-</code> y <code>[texto](https://…)</code>. Sin HTML.
                        Las imágenes se escriben <code>![descripción](/img/mapa.png)</code> con
                        una dirección https o una ruta del sitio: no se suben archivos.
                    </p>
                    {errorDe("cuerpo") !== undefined && (
                        <p className="error-campo">{errorDe("cuerpo")}</p>
                    )}
                </div>

                <div>
                    <label className="etiqueta" htmlFor="video">
                        Video{" "}
                        <span className="font-normal text-tinta-suave">(opcional)</span>
                    </label>
                    <input
                        id="video"
                        className="campo"
                        value={formulario.video_url}
                        onChange={(evento) => cambiar("video_url", evento.target.value)}
                        placeholder="https://www.youtube.com/watch?v=…"
                        autoComplete="off"
                        spellCheck={false}
                    />
                    <p className="ayuda">
                        De YouTube (también vale el código «Insertar»), Wordwall o Educaplay.
                        El video no se aloja: se incrusta junto al texto.
                    </p>
                    {errorDe("video_url") !== undefined && (
                        <p className="error-campo">{errorDe("video_url")}</p>
                    )}
                </div>

                <div>
                    <label className="etiqueta" htmlFor="fuente">
                        Fuente bibliográfica{" "}
                        <span className="font-normal text-tinta-suave">(opcional)</span>
                    </label>
                    <select
                        id="fuente"
                        className="campo"
                        value={formulario.fuente_id}
                        onChange={(evento) => cambiar("fuente_id", evento.target.value)}
                    >
                        <option value="">Sin fuente</option>
                        {fuentes.map((fuente) => (
                            <option key={fuente.id} value={fuente.id}>
                                {fuente.titulo}
                            </option>
                        ))}
                    </select>
                    {errorDe("fuente_id") !== undefined && (
                        <p className="error-campo">{errorDe("fuente_id")}</p>
                    )}
                </div>

                <div className="pie-formulario">
                    <button type="submit" className="boton" disabled={guardando}>
                        {guardando ? "Guardando…" : "Guardar"}
                    </button>

                    <Link to="/paginas" className="enlace text-sm text-tinta-suave">
                        Cancelar
                    </Link>
                </div>
            </form>

            {pagina !== null && (
                <div className="tarjeta grid gap-3 p-5 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="flex items-center gap-2 font-medium">
                                <span
                                    className={
                                        pagina.revisado ? "marca-revision" : "marca-revision-hueca"
                                    }
                                    aria-hidden="true"
                                />
                                Revisión lingüística
                            </p>
                            <p className="mt-0.5 text-xs text-tinta-suave">
                                {pagina.revisado
                                    ? `Revisada${pagina.revisado_por != null ? ` por ${pagina.revisado_por.nombre}` : ""}. Se publica.`
                                    : "Sin revisar. No llega al sitio público."}
                            </p>
                        </div>

                        {sesion.es_administrador ? (
                            <button
                                type="button"
                                className="boton-secundario"
                                disabled={guardando}
                                onClick={() => void alternarRevision()}
                            >
                                {pagina.revisado ? <IconoDesactivar /> : <IconoActivar />}
                                {pagina.revisado ? "Quitar la revisión" : "Marcar como revisada"}
                            </button>
                        ) : (
                            <p className="text-xs text-tinta-suave">
                                Solo el validador lingüístico la marca.
                            </p>
                        )}
                    </div>

                    {pagina.creado_por != null && (
                        <p className="text-xs text-tinta-suave">
                            Cargada por {pagina.creado_por.nombre}.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
