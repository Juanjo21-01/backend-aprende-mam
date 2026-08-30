/**
 * Alta y edición de un tema.
 *
 * Dos detalles del dominio que la pantalla tiene que respetar:
 *
 * - **`nombre_mam` pasa por el normalizador y `slug` no.** El primero es texto en Mam y se
 *   sanea al guardar, como el lema de una entrada; el segundo va en la URL del sitio
 *   estático, donde el apóstrofo tipográfico y la `ẍ` no tienen nada que hacer.
 * - **Cambiar el `slug` de un tema publicado rompe su dirección.** El sitio se compila con
 *   una ruta por tema, así que el enlace viejo deja de existir. Se avisa; la decisión es del
 *   docente.
 */

import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";

import { ErrorApi } from "../api/cliente";
import {
    actualizarCategoria,
    crearCategoria,
    obtenerCategoria,
} from "../api/recursos";
import { Aviso, Cargando } from "../componentes/Estados";
import { usePanel } from "../panel";
import type { Categoria, DatosCategoria } from "../tipos";

interface Formulario {
    nombre_es: string;
    nombre_mam: string;
    slug: string;
    icono: string;
    orden: string;
    padre_id: string;
}

const VACIO: Formulario = {
    nombre_es: "",
    nombre_mam: "",
    slug: "",
    icono: "",
    orden: "",
    padre_id: "",
};

/**
 * La dirección que propone el formulario mientras nadie la haya tocado.
 *
 * Se calcula sobre el nombre en castellano y nunca sobre el nombre en Mam: quitar los
 * diacríticos de «ẍoq’» daría «xoq», que es otra palabra.
 */
function aDireccion(texto: string): string {
    return texto
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
}

function desdeCategoria(tema: Categoria): Formulario {
    return {
        nombre_es: tema.nombre_es,
        nombre_mam: tema.nombre_mam ?? "",
        slug: tema.slug,
        icono: tema.icono ?? "",
        orden: tema.orden.toString(),
        padre_id: tema.padre_id?.toString() ?? "",
    };
}

function aDatos(formulario: Formulario): DatosCategoria {
    const texto = (valor: string): string | null =>
        valor.trim() === "" ? null : valor;

    return {
        nombre_es: formulario.nombre_es,
        nombre_mam: texto(formulario.nombre_mam),
        slug: formulario.slug,
        icono: texto(formulario.icono),
        orden: formulario.orden === "" ? null : Number(formulario.orden),
        padre_id: formulario.padre_id === "" ? null : Number(formulario.padre_id),
    };
}

export function FormularioCategoria() {
    const { id } = useParams();
    const navegar = useNavigate();
    const { categorias, recargarCatalogos } = usePanel();

    const esAlta = id === undefined;

    const [formulario, setFormulario] = useState<Formulario>(VACIO);
    const [original, setOriginal] = useState<Categoria | null>(null);
    const [cargando, setCargando] = useState(!esAlta);
    const [guardando, setGuardando] = useState(false);
    const [fallo, setFallo] = useState<ErrorApi | Error | null>(null);
    const [guardado, setGuardado] = useState<Categoria | null>(null);
    const [tecleado, setTecleado] = useState("");
    const [direccionTocada, setDireccionTocada] = useState(false);

    useEffect(() => {
        if (esAlta) {
            setFormulario(VACIO);
            setOriginal(null);
            setCargando(false);

            return;
        }

        let cancelado = false;
        setCargando(true);

        obtenerCategoria(Number(id))
            .then((tema) => {
                if (cancelado) {
                    return;
                }

                setOriginal(tema);
                setFormulario(desdeCategoria(tema));
                setDireccionTocada(true);
                setFallo(null);
            })
            .catch((error: unknown) => {
                if (!cancelado) {
                    setFallo(
                        error instanceof Error
                            ? error
                            : new Error("No se pudo cargar el tema."),
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

    function cambiarNombre(valor: string): void {
        setFormulario((previo) => ({
            ...previo,
            nombre_es: valor,
            slug: direccionTocada ? previo.slug : aDireccion(valor),
        }));
    }

    function cambiar<C extends keyof Formulario>(
        campo: C,
        valor: Formulario[C],
    ): void {
        setFormulario((previo) => ({ ...previo, [campo]: valor }));
    }

    async function guardar(): Promise<void> {
        setGuardando(true);
        setFallo(null);
        setGuardado(null);
        setTecleado(formulario.nombre_mam);

        try {
            const datos = aDatos(formulario);

            const grabado = esAlta
                ? await crearCategoria(datos)
                : await actualizarCategoria(Number(id), datos);

            // El selector de temas del formulario de entradas usa esta lista.
            await recargarCatalogos();

            if (esAlta) {
                navegar("/categorias");

                return;
            }

            setOriginal(grabado);
            setFormulario(desdeCategoria(grabado));
            setGuardado(grabado);
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

    const corregido =
        guardado !== null &&
        tecleado.trim() !== "" &&
        (guardado.nombre_mam ?? "") !== tecleado;

    const direccionCambiada =
        original !== null && guardado !== null && original.slug !== guardado.slug;

    // Un tema no puede ser su propio padre; el backend lo rechaza y aquí ni se ofrece.
    const posiblesPadres = categorias.filter((tema) => tema.id !== original?.id);

    if (cargando) {
        return <Cargando texto="Cargando el tema…" />;
    }

    return (
        <div className="grid max-w-2xl gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold">
                    {esAlta ? "Nuevo tema" : "Editar tema"}
                </h1>

                <Link
                    to="/categorias"
                    className="text-sm text-tenue underline-offset-2 hover:underline"
                >
                    Volver a los temas
                </Link>
            </div>

            {errorGeneral !== null && <Aviso>{errorGeneral}</Aviso>}

            {guardado !== null && (
                <div className="tarjeta px-4 py-3 text-sm" role="status">
                    <p className="text-ok">
                        Se guardó{" "}
                        <strong className="font-semibold">{guardado.nombre_es}</strong>.
                    </p>

                    {corregido && (
                        <p className="mt-1 text-tenue">
                            El nombre en Mam se escribió «{tecleado}» y quedó guardado como «
                            {guardado.nombre_mam}»: el panel corrige la ortografía al guardar.
                        </p>
                    )}

                    {direccionCambiada && (
                        <p className="mt-1 text-tenue">
                            Cambió la dirección del tema. En la próxima publicación, el enlace
                            viejo del sitio deja de existir.
                        </p>
                    )}
                </div>
            )}

            <form
                className="tarjeta grid gap-4 p-5"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    void guardar();
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="etiqueta" htmlFor="nombre_es">
                            Nombre en castellano
                        </label>
                        <input
                            id="nombre_es"
                            className="campo"
                            value={formulario.nombre_es}
                            onChange={(evento) => cambiarNombre(evento.target.value)}
                            required
                            autoFocus
                            autoComplete="off"
                        />
                        {errorDe("nombre_es") !== undefined && (
                            <p className="mt-1 text-xs text-error">
                                {errorDe("nombre_es")}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="nombre_mam">
                            Nombre en Mam{" "}
                            <span className="font-normal text-tenue">(opcional)</span>
                        </label>
                        <input
                            id="nombre_mam"
                            className="campo"
                            value={formulario.nombre_mam}
                            onChange={(evento) =>
                                cambiar("nombre_mam", evento.target.value)
                            }
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <p className="mt-1 text-xs text-tenue">
                            Se corrige al guardar, igual que las palabras del diccionario.
                        </p>
                        {errorDe("nombre_mam") !== undefined && (
                            <p className="mt-1 text-xs text-error">
                                {errorDe("nombre_mam")}
                            </p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="etiqueta" htmlFor="slug">
                        Dirección en el sitio
                    </label>
                    <input
                        id="slug"
                        className="campo font-mono text-xs"
                        value={formulario.slug}
                        onChange={(evento) => {
                            setDireccionTocada(true);
                            cambiar("slug", evento.target.value);
                        }}
                        required
                        autoComplete="off"
                        spellCheck={false}
                    />
                    <p className="mt-1 text-xs text-tenue">
                        Solo minúsculas, números y guiones. Se propone sola a partir del
                        nombre; si el tema ya está publicado, cambiarla rompe su enlace.
                    </p>
                    {errorDe("slug") !== undefined && (
                        <p className="mt-1 text-xs text-error">{errorDe("slug")}</p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label className="etiqueta" htmlFor="icono">
                            Ícono{" "}
                            <span className="font-normal text-tenue">(opcional)</span>
                        </label>
                        <input
                            id="icono"
                            className="campo"
                            value={formulario.icono}
                            onChange={(evento) => cambiar("icono", evento.target.value)}
                            autoComplete="off"
                        />
                        {errorDe("icono") !== undefined && (
                            <p className="mt-1 text-xs text-error">{errorDe("icono")}</p>
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
                            max={65535}
                            value={formulario.orden}
                            onChange={(evento) => cambiar("orden", evento.target.value)}
                        />
                        <p className="mt-1 text-xs text-tenue">Menor va primero.</p>
                        {errorDe("orden") !== undefined && (
                            <p className="mt-1 text-xs text-error">{errorDe("orden")}</p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="padre_id">
                            Dentro de
                        </label>
                        <select
                            id="padre_id"
                            className="campo"
                            value={formulario.padre_id}
                            onChange={(evento) =>
                                cambiar("padre_id", evento.target.value)
                            }
                        >
                            <option value="">Tema principal</option>
                            {posiblesPadres.map((tema) => (
                                <option key={tema.id} value={tema.id}>
                                    {tema.nombre_es}
                                </option>
                            ))}
                        </select>
                        {errorDe("padre_id") !== undefined && (
                            <p className="mt-1 text-xs text-error">
                                {errorDe("padre_id")}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3 border-t border-borde pt-4">
                    <button type="submit" className="boton" disabled={guardando}>
                        {guardando ? "Guardando…" : "Guardar"}
                    </button>

                    <Link
                        to="/categorias"
                        className="text-sm text-tenue underline-offset-2 hover:underline"
                    >
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    );
}
