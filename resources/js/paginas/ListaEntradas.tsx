/**
 * Listado de entradas: buscar, filtrar, revisar y borrar.
 *
 * Tres cosas que no son cosméticas:
 *
 * - **Los filtros viven en la URL.** Recargar, volver atrás o dejar el navegador abierto en
 *   la página 7 de «sin revisar» sigue funcionando. Revisando seis mil entradas eso deja de
 *   ser un lujo.
 * - **El orden lo pone el servidor** (`enOrdenMam`), y aquí no se reordena nada. El orden
 *   alfabético del Mam no es el del castellano, y ordenar en el cliente por `mam` daría un
 *   resultado equivocado que además parecería correcto.
 * - **La selección se limpia al cambiar de filtro o de página.** Firmar la revisión de
 *   entradas que ya no se están viendo es exactamente lo que no debe poder pasar.
 */

import { useCallback, useEffect, useRef, useState } from "react";
import { Link, useSearchParams } from "react-router";

import {
    borrarEntrada,
    listarEntradas,
    marcarRevision,
    marcarRevisionEnLote,
} from "../api/recursos";
import { Aviso, Cargando, Vacio } from "../componentes/Estados";
import { usePanel } from "../panel";
import type { Entrada, FiltrosEntradas, Pagina } from "../tipos";
import { SIN_ASIGNAR } from "../tipos";

const POR_PAGINA = [25, 50, 100] as const;

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

/** Un filtro de la URL, tal como lo espera la API: sin valor, la palabra centinela, o un id. */
function comoFiltro(valor: string): FiltrosEntradas["categoria"] {
    if (valor === "") {
        return undefined;
    }

    return valor === SIN_ASIGNAR ? SIN_ASIGNAR : Number(valor);
}

export function ListaEntradas() {
    const { sesion, categorias, fuentes } = usePanel();
    const [params, setParams] = useSearchParams();

    const buscar = params.get("buscar") ?? "";
    const revisado = params.get("revisado") ?? "";
    const categoria = params.get("categoria") ?? "";
    const fuente = params.get("fuente") ?? "";
    const porPagina = params.get("por_pagina") ?? "50";
    const pagina = Number(params.get("page") ?? "1");

    // El buscador se teclea aquí y llega a la URL con retardo: sin eso, escribir una palabra
    // de cinco letras dispara cinco consultas y pinta cinco veces la tabla.
    const [texto, setTexto] = useState(buscar);
    const [resultado, setResultado] = useState<Pagina<Entrada> | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [hecho, setHecho] = useState<string | null>(null);
    const [ocupada, setOcupada] = useState<number | null>(null);
    const [seleccion, setSeleccion] = useState<number[]>([]);
    const [enLote, setEnLote] = useState(false);

    const casilleroDeTodas = useRef<HTMLInputElement>(null);

    const claveDeFiltros = params.toString();

    const cambiarFiltro = useCallback(
        (clave: string, valor: string): void => {
            const siguiente = new URLSearchParams(params);

            if (valor === "") {
                siguiente.delete(clave);
            } else {
                siguiente.set(clave, valor);
            }

            // Cambiar un filtro devuelve a la primera página: quedarse en la 7 de un
            // resultado que ahora tiene dos páginas muestra una tabla vacía sin decir por qué.
            if (clave !== "page") {
                siguiente.delete("page");
            }

            setParams(siguiente);
        },
        [params, setParams],
    );

    const cargar = useCallback(async (): Promise<void> => {
        setCargando(true);

        try {
            setResultado(
                await listarEntradas({
                    buscar: buscar === "" ? undefined : buscar,
                    revisado: revisado === "" ? undefined : revisado === "1",
                    categoria: comoFiltro(categoria),
                    fuente: comoFiltro(fuente),
                    por_pagina: Number(porPagina),
                    page: pagina,
                }),
            );
            setError(null);
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setCargando(false);
        }
    }, [buscar, revisado, categoria, fuente, porPagina, pagina]);

    useEffect(() => {
        void cargar();
    }, [cargar]);

    // Cambió lo que se está viendo: lo seleccionado antes ya no está en pantalla.
    useEffect(() => {
        setSeleccion([]);
        setHecho(null);
    }, [claveDeFiltros]);

    // La casilla de búsqueda llega a la URL con retardo; el efecto de abajo recoge el
    // camino inverso, cuando `buscar` cambia por otra vía (volver atrás, por ejemplo).
    useEffect(() => {
        if (texto === buscar) {
            return;
        }

        const temporizador = setTimeout(() => cambiarFiltro("buscar", texto), 300);

        return () => clearTimeout(temporizador);
    }, [texto, buscar, cambiarFiltro]);

    useEffect(() => {
        setTexto(buscar);
    }, [buscar]);

    const visibles = resultado?.data ?? [];

    // «Algunas, no todas» no se puede expresar con un atributo: hay que ponerlo a mano.
    useEffect(() => {
        if (casilleroDeTodas.current !== null) {
            casilleroDeTodas.current.indeterminate =
                seleccion.length > 0 && seleccion.length < visibles.length;
        }
    }, [seleccion, visibles.length]);

    function reemplazarFila(entrada: Entrada): void {
        setResultado((previo) =>
            previo === null
                ? previo
                : {
                      ...previo,
                      data: previo.data.map((fila) =>
                          fila.id === entrada.id ? entrada : fila,
                      ),
                  },
        );
    }

    function alternarSeleccion(id: number): void {
        setSeleccion((previa) =>
            previa.includes(id)
                ? previa.filter((otro) => otro !== id)
                : [...previa, id],
        );
    }

    async function alternarRevision(entrada: Entrada): Promise<void> {
        setOcupada(entrada.id);
        setError(null);

        try {
            reemplazarFila(await marcarRevision(entrada.id, !entrada.revisado));
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setOcupada(null);
        }
    }

    async function revisarSeleccion(revisada: boolean): Promise<void> {
        setEnLote(true);
        setError(null);
        setHecho(null);

        try {
            const { recibidas, actualizadas } = await marcarRevisionEnLote(
                seleccion,
                revisada,
            );

            const comoQuedan = revisada ? "revisadas" : "sin revisar";

            // El caso de no cambiar nada merece su propia frase: refirmar una página ya
            // firmada es normal, y «se marcaron 0 entradas» se lee como si hubiera fallado.
            const mensaje =
                actualizadas === 0
                    ? `Las ${recibidas} ya estaban ${comoQuedan}: no cambió ninguna.`
                    : `${actualizadas} entrada(s) quedaron ${comoQuedan}.` +
                      (recibidas > actualizadas
                          ? ` Las otras ${recibidas - actualizadas} ya estaban así.`
                          : "");

            setSeleccion([]);
            await cargar();
            setHecho(mensaje);
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setEnLote(false);
        }
    }

    async function borrar(entrada: Entrada): Promise<void> {
        if (!window.confirm(`¿Borrar «${entrada.mam}»? Esto no se puede deshacer.`)) {
            return;
        }

        setOcupada(entrada.id);
        setError(null);

        try {
            await borrarEntrada(entrada.id);
            await cargar();
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setOcupada(null);
        }
    }

    const meta = resultado?.meta;
    const sinFiltros =
        buscar === "" && revisado === "" && categoria === "" && fuente === "";

    // Solo el validador lingüístico firma revisiones, así que a un editor no se le ofrecen
    // las casillas: no tendría qué hacer con ellas.
    const puedeFirmar = sesion.es_administrador;

    return (
        <div className="grid gap-5">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Entradas</h1>
                    <p className="mt-1 text-sm text-tinta-suave">
                        Al sitio público solo llegan las entradas revisadas.
                    </p>
                </div>

                <Link to="/entradas/nueva" className="boton">
                    Nueva entrada
                </Link>
            </div>

            <div className="tarjeta grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="sm:col-span-2">
                    <label className="etiqueta" htmlFor="buscar">
                        Buscar
                    </label>
                    <input
                        id="buscar"
                        type="search"
                        className="campo"
                        value={texto}
                        onChange={(evento) => setTexto(evento.target.value)}
                        placeholder="En Mam o en castellano"
                        autoComplete="off"
                    />
                    <p className="mt-1 text-xs text-tinta-suave">
                        Da igual el saltillo y las mayúsculas: la búsqueda usa la misma clave
                        que calculó el sistema al guardar.
                    </p>
                </div>

                <div>
                    <label className="etiqueta" htmlFor="revisado">
                        Revisión
                    </label>
                    <select
                        id="revisado"
                        className="campo"
                        value={revisado}
                        onChange={(evento) =>
                            cambiarFiltro("revisado", evento.target.value)
                        }
                    >
                        <option value="">Todas</option>
                        <option value="1">Revisadas</option>
                        <option value="0">Sin revisar</option>
                    </select>
                </div>

                <div>
                    <label className="etiqueta" htmlFor="categoria">
                        Tema
                    </label>
                    <select
                        id="categoria"
                        className="campo"
                        value={categoria}
                        onChange={(evento) =>
                            cambiarFiltro("categoria", evento.target.value)
                        }
                    >
                        <option value="">Todos</option>
                        {/* Antes de publicar, toda entrada debería llevar tema. Esta opción
                            es la única forma de encontrar las que no lo tienen. */}
                        <option value={SIN_ASIGNAR}>Sin tema</option>
                        {categorias.map((tema) => (
                            <option key={tema.id} value={tema.id}>
                                {tema.nombre_es}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="sm:col-span-2">
                    <label className="etiqueta" htmlFor="fuente">
                        Fuente
                    </label>
                    <select
                        id="fuente"
                        className="campo"
                        value={fuente}
                        onChange={(evento) =>
                            cambiarFiltro("fuente", evento.target.value)
                        }
                    >
                        <option value="">Todas</option>
                        <option value={SIN_ASIGNAR}>Sin fuente</option>
                        {fuentes.map((libro) => (
                            <option key={libro.id} value={libro.id}>
                                {libro.titulo}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-tinta-suave">
                        Para revisar de un tirón lo que acabás de transcribir de un libro.
                    </p>
                </div>
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

            {hecho !== null && (
                <div className="tarjeta px-4 py-3 text-sm text-jade" role="status">
                    {hecho}
                </div>
            )}

            {puedeFirmar && seleccion.length > 0 && (
                <div className="tarjeta flex flex-wrap items-center gap-3 px-4 py-3">
                    <p className="text-sm">
                        {seleccion.length} seleccionada(s)
                    </p>

                    <button
                        type="button"
                        className="boton"
                        disabled={enLote}
                        onClick={() => void revisarSeleccion(true)}
                    >
                        {enLote ? "Guardando…" : "Marcar como revisadas"}
                    </button>

                    <button
                        type="button"
                        className="boton-secundario"
                        disabled={enLote}
                        onClick={() => void revisarSeleccion(false)}
                    >
                        Quitar la revisión
                    </button>

                    <button
                        type="button"
                        className="text-sm text-tinta-suave underline-offset-2 hover:underline"
                        onClick={() => setSeleccion([])}
                    >
                        Limpiar
                    </button>
                </div>
            )}

            <div className="tarjeta overflow-x-auto">
                {cargando && resultado === null ? (
                    <Cargando />
                ) : visibles.length === 0 ? (
                    <Vacio>
                        {sinFiltros
                            ? "Todavía no hay entradas. La primera se carga con «Nueva entrada»."
                            : "Ninguna entrada coincide con estos filtros."}
                    </Vacio>
                ) : (
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-b border-borde text-left text-xs text-tinta-suave">
                                {puedeFirmar && (
                                    <th className="w-8 px-4 py-2">
                                        <input
                                            ref={casilleroDeTodas}
                                            type="checkbox"
                                            aria-label="Seleccionar todas las de esta página"
                                            checked={
                                                seleccion.length === visibles.length &&
                                                visibles.length > 0
                                            }
                                            onChange={(evento) =>
                                                setSeleccion(
                                                    evento.target.checked
                                                        ? visibles.map((fila) => fila.id)
                                                        : [],
                                                )
                                            }
                                        />
                                    </th>
                                )}
                                <th className="px-4 py-2 font-medium">Mam</th>
                                <th className="px-4 py-2 font-medium">Castellano</th>
                                <th className="px-4 py-2 font-medium">Clase</th>
                                <th className="px-4 py-2 font-medium">Temas</th>
                                <th className="px-4 py-2 font-medium">Revisión</th>
                                <th className="px-4 py-2 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.map((entrada) => (
                                <tr
                                    key={entrada.id}
                                    className="border-b border-borde last:border-0"
                                >
                                    {puedeFirmar && (
                                        <td className="px-4 py-2.5 align-top">
                                            <input
                                                type="checkbox"
                                                aria-label={`Seleccionar ${entrada.mam}`}
                                                checked={seleccion.includes(entrada.id)}
                                                onChange={() =>
                                                    alternarSeleccion(entrada.id)
                                                }
                                            />
                                        </td>
                                    )}

                                    <td className="px-4 py-2.5 align-top">
                                        <Link
                                            to={`/entradas/${entrada.id}`}
                                            className="font-medium underline-offset-2 hover:underline"
                                        >
                                            {entrada.mam}
                                        </Link>
                                    </td>

                                    <td className="px-4 py-2.5 align-top">
                                        {entrada.espanol}
                                    </td>

                                    <td className="px-4 py-2.5 align-top text-tinta-suave">
                                        <span
                                            title={
                                                entrada.categoria_gramatical?.nombre ??
                                                undefined
                                            }
                                        >
                                            {entrada.categoria_gramatical?.abreviatura ??
                                                "—"}
                                        </span>
                                    </td>

                                    <td className="px-4 py-2.5 align-top">
                                        <span className="flex flex-wrap gap-1">
                                            {entrada.categorias !== undefined &&
                                            entrada.categorias.length > 0 ? (
                                                entrada.categorias.map((tema) => (
                                                    <span key={tema.id} className="chip">
                                                        {tema.nombre_es}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="text-tinta-suave">—</span>
                                            )}
                                        </span>
                                    </td>

                                    <td className="px-4 py-2.5 align-top">
                                        {puedeFirmar ? (
                                            <button
                                                type="button"
                                                className={`text-xs underline-offset-2 hover:underline disabled:opacity-50 ${
                                                    entrada.revisado
                                                        ? "text-jade"
                                                        : "text-tinta-suave"
                                                }`}
                                                disabled={ocupada === entrada.id}
                                                onClick={() => void alternarRevision(entrada)}
                                                title={
                                                    entrada.revisado
                                                        ? "Quitar la revisión la saca de la publicación"
                                                        : "Marcarla la incluye en la próxima publicación"
                                                }
                                            >
                                                {entrada.revisado
                                                    ? "Revisada"
                                                    : "Sin revisar"}
                                            </button>
                                        ) : (
                                            <span
                                                className={`text-xs ${entrada.revisado ? "text-jade" : "text-tinta-suave"}`}
                                            >
                                                {entrada.revisado
                                                    ? "Revisada"
                                                    : "Sin revisar"}
                                            </span>
                                        )}
                                    </td>

                                    <td className="px-4 py-2.5 text-right align-top whitespace-nowrap">
                                        <Link
                                            to={`/entradas/${entrada.id}`}
                                            className="text-xs underline-offset-2 hover:underline"
                                        >
                                            Editar
                                        </Link>

                                        {sesion.es_administrador && (
                                            <button
                                                type="button"
                                                className="ml-3 text-xs text-alerta underline-offset-2 hover:underline disabled:opacity-50"
                                                disabled={ocupada === entrada.id}
                                                onClick={() => void borrar(entrada)}
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

            {meta !== undefined && meta.total > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-tinta-suave">
                    <p>
                        {meta.from}–{meta.to} de {meta.total}
                        {cargando && " · actualizando…"}
                    </p>

                    {/* `flex-wrap`: sin él, los cuatro controles suman 326 px de ancho
                        mínimo y el panel entero se desplazaba en horizontal por debajo de
                        340 px de pantalla. */}
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="flex items-center gap-2 text-xs">
                            Por página
                            <select
                                className="campo w-auto py-1"
                                value={porPagina}
                                onChange={(evento) =>
                                    cambiarFiltro("por_pagina", evento.target.value)
                                }
                            >
                                {POR_PAGINA.map((cantidad) => (
                                    <option key={cantidad} value={cantidad}>
                                        {cantidad}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <button
                            type="button"
                            className="boton-secundario"
                            disabled={meta.current_page <= 1}
                            onClick={() =>
                                cambiarFiltro("page", String(meta.current_page - 1))
                            }
                        >
                            Anterior
                        </button>

                        <span className="text-xs">
                            {meta.current_page} de {meta.last_page}
                        </span>

                        <button
                            type="button"
                            className="boton-secundario"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() =>
                                cambiarFiltro("page", String(meta.current_page + 1))
                            }
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
