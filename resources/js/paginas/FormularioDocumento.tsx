/**
 * Alta y edición de un documento descargable.
 *
 * No hay subida de archivos: el PDF se coloca en el sitio (o en un CDN) y aquí se registra su
 * dirección. Así el cPanel compartido no sirve descargas de estudiantes, y el sitio las baja
 * bajo demanda sin precargarlas en el teléfono.
 */

import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";

import { ErrorApi } from "../api/cliente";
import {
    actualizarDocumento,
    crearDocumento,
    marcarRevisionDocumento,
    obtenerDocumento,
} from "../api/recursos";
import { Aviso, Cargando } from "../componentes/Estados";
import { IconoActivar, IconoDesactivar, IconoVolver } from "../componentes/Iconos";
import { usePanel } from "../panel";
import { TIPOS_DE_DOCUMENTO } from "../tipos";
import type { DatosDocumento, Documento, TipoDocumento } from "../tipos";

interface Formulario {
    slug: string;
    titulo: string;
    descripcion: string;
    tipo: TipoDocumento;
    archivo_url: string;
    tamano_kb: string;
    licencia: string;
    video_url: string;
    fuente_id: string;
    orden: string;
}

const VACIO: Formulario = {
    slug: "",
    titulo: "",
    descripcion: "",
    tipo: "guia",
    archivo_url: "",
    tamano_kb: "",
    licencia: "",
    video_url: "",
    fuente_id: "",
    orden: "0",
};

function desdeDocumento(documento: Documento): Formulario {
    return {
        slug: documento.slug,
        titulo: documento.titulo,
        descripcion: documento.descripcion ?? "",
        tipo: documento.tipo,
        archivo_url: documento.archivo_url,
        tamano_kb: documento.tamano_kb?.toString() ?? "",
        licencia: documento.licencia ?? "",
        video_url: documento.video_url ?? "",
        fuente_id: documento.fuente_id?.toString() ?? "",
        orden: String(documento.orden),
    };
}

function aDatos(formulario: Formulario): DatosDocumento {
    const texto = (valor: string): string | null => (valor.trim() === "" ? null : valor);

    return {
        slug: formulario.slug,
        titulo: formulario.titulo,
        descripcion: texto(formulario.descripcion),
        tipo: formulario.tipo,
        archivo_url: formulario.archivo_url,
        tamano_kb: formulario.tamano_kb === "" ? null : Number(formulario.tamano_kb),
        licencia: texto(formulario.licencia),
        video_url: texto(formulario.video_url),
        fuente_id: formulario.fuente_id === "" ? null : Number(formulario.fuente_id),
        orden: formulario.orden === "" ? 0 : Number(formulario.orden),
    };
}

export function FormularioDocumento() {
    const { id } = useParams();
    const navegar = useNavigate();
    const { sesion, fuentes } = usePanel();

    const esAlta = id === undefined;

    const [formulario, setFormulario] = useState<Formulario>(VACIO);
    const [documento, setDocumento] = useState<Documento | null>(null);
    const [cargando, setCargando] = useState(!esAlta);
    const [guardando, setGuardando] = useState(false);
    const [fallo, setFallo] = useState<ErrorApi | Error | null>(null);

    useEffect(() => {
        if (esAlta) {
            setFormulario(VACIO);
            setDocumento(null);
            setCargando(false);

            return;
        }

        let cancelado = false;
        setCargando(true);

        obtenerDocumento(Number(id))
            .then((cargado) => {
                if (!cancelado) {
                    setDocumento(cargado);
                    setFormulario(desdeDocumento(cargado));
                    setFallo(null);
                }
            })
            .catch((error: unknown) => {
                if (!cancelado) {
                    setFallo(
                        error instanceof Error
                            ? error
                            : new Error("No se pudo cargar el documento."),
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
                await crearDocumento(datos);
            } else {
                await actualizarDocumento(Number(id), datos);
            }

            navegar("/documentos");
        } catch (error) {
            setFallo(error instanceof Error ? error : new Error("No se pudo guardar."));
        } finally {
            setGuardando(false);
        }
    }

    async function alternarRevision(): Promise<void> {
        if (documento === null) {
            return;
        }

        setGuardando(true);
        setFallo(null);

        try {
            setDocumento(await marcarRevisionDocumento(documento.id, !documento.revisado));
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
        return <Cargando texto="Cargando el documento…" />;
    }

    return (
        <div className="grid max-w-2xl gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold">
                    {esAlta ? "Nuevo documento" : "Editar documento"}
                </h1>

                <Link
                    to="/documentos"
                    className="enlace inline-flex items-center gap-1 text-sm text-tinta-suave"
                >
                    <IconoVolver className="size-3.5" />
                    Volver a los documentos
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
                <div className="grid gap-4 sm:grid-cols-[1fr_11rem]">
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

                    <div>
                        <label className="etiqueta" htmlFor="tipo">
                            Tipo
                        </label>
                        <select
                            id="tipo"
                            className="campo"
                            value={formulario.tipo}
                            onChange={(evento) =>
                                cambiar("tipo", evento.target.value as TipoDocumento)
                            }
                        >
                            {TIPOS_DE_DOCUMENTO.map(({ valor, nombre }) => (
                                <option key={valor} value={valor}>
                                    {nombre}
                                </option>
                            ))}
                        </select>
                        {errorDe("tipo") !== undefined && (
                            <p className="error-campo">{errorDe("tipo")}</p>
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
                            placeholder="guia-de-lectura"
                            required
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <p className="ayuda">Solo minúsculas, números y guiones.</p>
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
                    <label className="etiqueta" htmlFor="descripcion">
                        Descripción{" "}
                        <span className="font-normal text-tinta-suave">(opcional)</span>
                    </label>
                    <textarea
                        id="descripcion"
                        className="campo min-h-24"
                        value={formulario.descripcion}
                        onChange={(evento) => cambiar("descripcion", evento.target.value)}
                    />
                    {errorDe("descripcion") !== undefined && (
                        <p className="error-campo">{errorDe("descripcion")}</p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                    <div>
                        <label className="etiqueta" htmlFor="archivo">
                            Dirección del PDF
                        </label>
                        <input
                            id="archivo"
                            className="campo"
                            value={formulario.archivo_url}
                            onChange={(evento) => cambiar("archivo_url", evento.target.value)}
                            placeholder="/materiales/guia-de-lectura.pdf"
                            required
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <p className="ayuda">
                            Ruta del sitio que empiece con «/» o dirección https. El archivo se
                            coloca aparte; aquí no se sube.
                        </p>
                        {errorDe("archivo_url") !== undefined && (
                            <p className="error-campo">{errorDe("archivo_url")}</p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="tamano">
                            Tamaño (KB)
                        </label>
                        <input
                            id="tamano"
                            className="campo"
                            type="number"
                            min={1}
                            value={formulario.tamano_kb}
                            onChange={(evento) => cambiar("tamano_kb", evento.target.value)}
                        />
                        {errorDe("tamano_kb") !== undefined && (
                            <p className="error-campo">{errorDe("tamano_kb")}</p>
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
                        <p className="error-campo">{errorDe("licencia")}</p>
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

                    <Link to="/documentos" className="enlace text-sm text-tinta-suave">
                        Cancelar
                    </Link>
                </div>
            </form>

            {documento !== null && (
                <div className="tarjeta grid gap-3 p-5 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="flex items-center gap-2 font-medium">
                                <span
                                    className={
                                        documento.revisado
                                            ? "marca-revision"
                                            : "marca-revision-hueca"
                                    }
                                    aria-hidden="true"
                                />
                                Revisión lingüística
                            </p>
                            <p className="mt-0.5 text-xs text-tinta-suave">
                                {documento.revisado
                                    ? `Revisado${documento.revisado_por != null ? ` por ${documento.revisado_por.nombre}` : ""}. Se publica.`
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
                                {documento.revisado ? <IconoDesactivar /> : <IconoActivar />}
                                {documento.revisado ? "Quitar la revisión" : "Marcar como revisado"}
                            </button>
                        ) : (
                            <p className="text-xs text-tinta-suave">
                                Solo el validador lingüístico lo marca.
                            </p>
                        )}
                    </div>

                    {documento.creado_por != null && (
                        <p className="text-xs text-tinta-suave">
                            Cargado por {documento.creado_por.nombre}.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
