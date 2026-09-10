/**
 * Listado de entradas: buscar, filtrar, revisar y borrar.
 *
 * Cuatro cosas que no son cosméticas:
 *
 * - **Los filtros viven en la URL.** Recargar, volver atrás o dejar el navegador abierto en
 *   la página 7 de «sin revisar» sigue funcionando. Revisando seis mil entradas eso deja de
 *   ser un lujo.
 * - **El orden lo pone el servidor** (`enOrdenMam`), y aquí no se reordena nada. El orden
 *   alfabético del Mam no es el del castellano, y ordenar en el cliente por `mam` daría un
 *   resultado equivocado que además parecería correcto.
 * - **La selección se limpia al cambiar de filtro o de página.** Firmar la revisión de
 *   entradas que ya no se están viendo es exactamente lo que no debe poder pasar.
 * - **Tabla a partir de 768 px, fichas por debajo**, y se dibuja una sola de las dos. El
 *   porqué de decidirlo en JavaScript y no escondiendo una con CSS está en `pantalla.ts`.
 */

import { useCallback, useEffect, useRef, useState } from "react";
import { Link, useSearchParams } from "react-router";

import {
    borrarEntrada,
    listarEntradas,
    marcarRevision,
    marcarRevisionEnLote,
} from "../api/recursos";
import { Confirmacion } from "../componentes/Confirmacion";
import { Aviso, Cargando, Exito, Vacio } from "../componentes/Estados";
import { usePanel } from "../panel";
import { useCabeLaTabla } from "../pantalla";
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

/**
 * El estado de revisión: un rombo y la palabra.
 *
 * El rombo es el de la cenefa y carga el único significado que el oro tiene en el panel:
 * revisada, o sea, esto lo va a ver un estudiante. «Sin revisar» es el mismo rombo sin
 * rellenar y no un hueco, para que la columna no baile de una fila a otra y se pueda recorrer
 * con la vista.
 *
 * El color no es el canal: el oro sobre papel da 2.9:1 y no pasa AA. Quien no lo distinga
 * sigue leyendo «Revisada», que es donde está la información. Por eso el rombo va
 * `aria-hidden`.
 */
function MarcaDeRevision({ revisado }: { revisado: boolean }) {
    return (
        <>
            <span
                className={revisado ? "marca-revision" : "marca-revision-hueca"}
                aria-hidden="true"
            />
            <span className={revisado ? undefined : "text-tinta-suave"}>
                {revisado ? "Revisada" : "Sin revisar"}
            </span>
        </>
    );
}

/**
 * Solo el validador lingüístico firma revisiones, así que a un editor esto se le enseña y no
 * se le ofrece: para él es un dato, no un interruptor.
 */
function ControlDeRevision({
    entrada,
    puedeFirmar,
    ocupada,
    alternar,
}: {
    entrada: Entrada;
    puedeFirmar: boolean;
    ocupada: boolean;
    alternar: () => void;
}) {
    if (!puedeFirmar) {
        return (
            <span className="inline-flex items-center gap-2 text-xs">
                <MarcaDeRevision revisado={entrada.revisado} />
            </span>
        );
    }

    return (
        <button
            type="button"
            className="enlace-accion -my-1 inline-flex items-center gap-2 disabled:opacity-50"
            disabled={ocupada}
            onClick={alternar}
            title={
                entrada.revisado
                    ? "Quitar la revisión la saca de la publicación"
                    : "Marcarla la incluye en la próxima publicación"
            }
        >
            <MarcaDeRevision revisado={entrada.revisado} />
        </button>
    );
}

/**
 * La clase de palabra, abreviada, en la tabla.
 *
 * Va en `<abbr>` y no en un `<span title>`: es el elemento que existe para esto y el único
 * que los lectores de pantalla anuncian de verdad. Un `title` suelto no lo lee casi ninguno,
 * y en una pantalla táctil no hay forma de sacarlo.
 *
 * En la ficha del teléfono no se usa: ahí hay sitio para el nombre entero, y «Sustantivo»
 * no necesita explicación.
 */
function ClaseDePalabra({ entrada }: { entrada: Entrada }) {
    const clase = entrada.categoria_gramatical;

    if (clase == null) {
        return <span>—</span>;
    }

    return <abbr title={clase.nombre}>{clase.abreviatura}</abbr>;
}

function TemasDeEntrada({ entrada }: { entrada: Entrada }) {
    if (entrada.categorias === undefined || entrada.categorias.length === 0) {
        return <span className="text-tinta-suave">—</span>;
    }

    return (
        <span className="flex flex-wrap gap-1">
            {entrada.categorias.map((tema) => (
                <span key={tema.id} className="chip-tema">
                    {tema.nombre_es}
                </span>
            ))}
        </span>
    );
}

function AccionesDeEntrada({
    entrada,
    esAdministrador,
    ocupada,
    borrar,
}: {
    entrada: Entrada;
    esAdministrador: boolean;
    ocupada: boolean;
    borrar: () => void;
}) {
    return (
        <>
            <Link to={`/entradas/${entrada.id}`} className="enlace-accion">
                Editar
            </Link>

            {esAdministrador && (
                <button
                    type="button"
                    className="enlace-peligro ml-3"
                    disabled={ocupada}
                    onClick={borrar}
                >
                    Borrar
                </button>
            )}
        </>
    );
}

export function ListaEntradas() {
    const { sesion, categorias, fuentes } = usePanel();
    const [params, setParams] = useSearchParams();
    const cabeLaTabla = useCabeLaTabla();

    const buscar = params.get("buscar") ?? "";
    const revisado = params.get("revisado") ?? "";
    const categoria = params.get("categoria") ?? "";
    const fuente = params.get("fuente") ?? "";
    const porPagina = params.get("por_pagina") ?? "50";
    const pagina = Number(params.get("page") ?? "1");

    // El buscador se teclea aquí y llega a la URL con retardo: sin eso, escribir una palabra
    // de cinco letras dispara cinco consultas y pinta cinco veces la lista.
    const [texto, setTexto] = useState(buscar);
    const [resultado, setResultado] = useState<Pagina<Entrada> | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [hecho, setHecho] = useState<string | null>(null);
    const [ocupada, setOcupada] = useState<number | null>(null);
    const [seleccion, setSeleccion] = useState<number[]>([]);
    const [enLote, setEnLote] = useState(false);
    const [aBorrar, setABorrar] = useState<Entrada | null>(null);

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
            // resultado que ahora tiene dos páginas muestra una lista vacía sin decir por qué.
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
        setABorrar(null);
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
    const conBarraDeLote = puedeFirmar && seleccion.length > 0;

    /** Los que se pliegan en el teléfono. El buscador no cuenta: se queda siempre a la vista. */
    const filtrosActivos = [revisado, categoria, fuente].filter(
        (valor) => valor !== "",
    ).length;

    const selectores = (
        <div className="grid gap-3 md:grid-cols-3">
            <div>
                <label className="etiqueta" htmlFor="revisado">
                    Revisión
                </label>
                <select
                    id="revisado"
                    className="campo"
                    value={revisado}
                    onChange={(evento) => cambiarFiltro("revisado", evento.target.value)}
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
                    onChange={(evento) => cambiarFiltro("categoria", evento.target.value)}
                >
                    <option value="">Todos</option>
                    {/* Antes de publicar, toda entrada debería llevar tema. Esta opción es la
                        única forma de encontrar las que no lo tienen. */}
                    <option value={SIN_ASIGNAR}>Sin tema</option>
                    {categorias.map((tema) => (
                        <option key={tema.id} value={tema.id}>
                            {tema.nombre_es}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label className="etiqueta" htmlFor="fuente">
                    Fuente
                </label>
                <select
                    id="fuente"
                    className="campo"
                    value={fuente}
                    onChange={(evento) => cambiarFiltro("fuente", evento.target.value)}
                >
                    <option value="">Todas</option>
                    <option value={SIN_ASIGNAR}>Sin fuente</option>
                    {fuentes.map((libro) => (
                        <option key={libro.id} value={libro.id}>
                            {libro.titulo}
                        </option>
                    ))}
                </select>
                <p className="ayuda">
                    Para revisar de un tirón lo que acabás de transcribir de un libro.
                </p>
            </div>
        </div>
    );

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

            <div className="tarjeta grid gap-3 p-4">
                <div>
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
                    <p className="ayuda">
                        Da igual el saltillo y las mayúsculas: la búsqueda usa la misma clave
                        que calculó el sistema al guardar.
                    </p>
                </div>

                {/*
                    En el teléfono los tres selectores se pliegan y el buscador se queda
                    fuera: el buscador se usa en cada consulta, y tres desplegables abiertos
                    empujan la primera palabra fuera de la pantalla. El resumen dice cuántos
                    filtros hay puestos, para que plegarlos no sea esconderlos.
                */}
                {cabeLaTabla ? (
                    selectores
                ) : (
                    <details className="group">
                        <summary className="flex cursor-pointer list-none items-center gap-2 py-1 text-sm font-medium [&::-webkit-details-marker]:hidden">
                            Filtros
                            {filtrosActivos > 0 && (
                                <span className="chip-nota">
                                    {filtrosActivos} activo{filtrosActivos > 1 ? "s" : ""}
                                </span>
                            )}
                            <span className="ml-auto text-xs font-normal text-tinta-suave group-open:hidden">
                                Mostrar
                            </span>
                            <span className="ml-auto hidden text-xs font-normal text-tinta-suave group-open:inline">
                                Ocultar
                            </span>
                        </summary>

                        <div className="mt-3">{selectores}</div>
                    </details>
                )}
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

            {hecho !== null && <Exito>{hecho}</Exito>}

            <div className="tarjeta">
                {cargando && resultado === null ? (
                    <Cargando />
                ) : visibles.length === 0 ? (
                    sinFiltros ? (
                        <Vacio
                            titulo="Todavía no hay entradas"
                            accion={
                                <Link to="/entradas/nueva" className="boton">
                                    Cargar la primera
                                </Link>
                            }
                        >
                            Cada palabra conserva su fuente bibliográfica y no sale al sitio
                            público hasta que alguien la revisa.
                        </Vacio>
                    ) : (
                        <Vacio>Ninguna entrada coincide con estos filtros.</Vacio>
                    )
                ) : cabeLaTabla ? (
                    <table className="tabla">
                        <thead>
                            <tr>
                                {puedeFirmar && (
                                    <th className="w-8">
                                        <label className="casilla">
                                            <input
                                                ref={casilleroDeTodas}
                                                type="checkbox"
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
                                            <span className="sr-only">
                                                Seleccionar todas las de esta página
                                            </span>
                                        </label>
                                    </th>
                                )}
                                <th>Mam</th>
                                <th>Castellano</th>
                                <th>Clase</th>
                                <th>Temas</th>
                                <th>Revisión</th>
                                <th className="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.map((entrada) => (
                                <tr key={entrada.id}>
                                    {puedeFirmar && (
                                        <td>
                                            <label className="casilla">
                                                <input
                                                    type="checkbox"
                                                    checked={seleccion.includes(entrada.id)}
                                                    onChange={() =>
                                                        alternarSeleccion(entrada.id)
                                                    }
                                                />
                                                <span className="sr-only">
                                                    Seleccionar {entrada.mam}
                                                </span>
                                            </label>
                                        </td>
                                    )}

                                    <td>
                                        <Link
                                            to={`/entradas/${entrada.id}`}
                                            className="enlace mam font-bold"
                                            lang="mam"
                                        >
                                            {entrada.mam}
                                        </Link>
                                    </td>

                                    <td>{entrada.espanol}</td>

                                    <td className="text-tinta-suave">
                                        <ClaseDePalabra entrada={entrada} />
                                    </td>

                                    <td>
                                        <TemasDeEntrada entrada={entrada} />
                                    </td>

                                    <td>
                                        <ControlDeRevision
                                            entrada={entrada}
                                            puedeFirmar={puedeFirmar}
                                            ocupada={ocupada === entrada.id}
                                            alternar={() => void alternarRevision(entrada)}
                                        />
                                    </td>

                                    <td className="text-right whitespace-nowrap">
                                        <AccionesDeEntrada
                                            entrada={entrada}
                                            esAdministrador={sesion.es_administrador}
                                            ocupada={ocupada === entrada.id}
                                            borrar={() => setABorrar(entrada)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <ul className="lista-fichas">
                        {puedeFirmar && (
                            <li className="ficha">
                                <label className="casilla text-xs text-tinta-suave">
                                    <input
                                        ref={casilleroDeTodas}
                                        type="checkbox"
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
                                    Seleccionar las {visibles.length} de esta página
                                </label>
                            </li>
                        )}

                        {visibles.map((entrada) => (
                            <li key={entrada.id} className="ficha">
                                <div className="flex items-start gap-3">
                                    {puedeFirmar && (
                                        <label className="casilla mt-1">
                                            <input
                                                type="checkbox"
                                                checked={seleccion.includes(entrada.id)}
                                                onChange={() =>
                                                    alternarSeleccion(entrada.id)
                                                }
                                            />
                                            <span className="sr-only">
                                                Seleccionar {entrada.mam}
                                            </span>
                                        </label>
                                    )}

                                    <div className="min-w-0 flex-1">
                                        <Link
                                            to={`/entradas/${entrada.id}`}
                                            className="enlace mam text-base font-bold"
                                            lang="mam"
                                        >
                                            {entrada.mam}
                                        </Link>
                                        <p className="text-sm">{entrada.espanol}</p>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-tinta-suave">
                                    {entrada.categoria_gramatical != null && (
                                        <span>{entrada.categoria_gramatical.nombre}</span>
                                    )}
                                    <TemasDeEntrada entrada={entrada} />
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-borde pt-2">
                                    <ControlDeRevision
                                        entrada={entrada}
                                        puedeFirmar={puedeFirmar}
                                        ocupada={ocupada === entrada.id}
                                        alternar={() => void alternarRevision(entrada)}
                                    />

                                    <div className="whitespace-nowrap">
                                        <AccionesDeEntrada
                                            entrada={entrada}
                                            esAdministrador={sesion.es_administrador}
                                            ocupada={ocupada === entrada.id}
                                            borrar={() => setABorrar(entrada)}
                                        />
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/*
                En el teléfono la barra se queda pegada al borde inferior mientras se
                recorre la lista. Antes iba en el flujo, encima: al bajar a seleccionar la
                décima entrada, los botones para firmarlas ya se habían quedado fuera de la
                pantalla.

                `sticky` y no `fixed`: un elemento fijo sale del flujo y hay que reservarle
                sitio a mano con un relleno inferior adivinado. Con 111 px de barra contra 96
                de relleno, la paginación se salvaba por nueve píxeles. Pegajoso ocupa su
                propio hueco y el problema no existe.

                Va después de la lista y no antes porque es lo que se hace al terminar de
                seleccionar, y porque `sticky bottom-0` solo flota sobre lo que tiene encima.
            */}
            {conBarraDeLote && (
                <div className="tarjeta flex flex-wrap items-center gap-3 px-4 py-3 max-md:sticky max-md:bottom-0 max-md:z-20 max-md:-mx-4 max-md:rounded-none max-md:border-x-0">
                    <p className="text-sm">{seleccion.length} seleccionada(s)</p>

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
                        className="enlace text-sm text-tinta-suave"
                        onClick={() => setSeleccion([])}
                    >
                        Limpiar
                    </button>
                </div>
            )}

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

            {aBorrar !== null && (
                <Confirmacion
                    titulo="¿Borrar esta entrada?"
                    textoConfirmar="Borrar la entrada"
                    peligro
                    ocupado={ocupada === aBorrar.id}
                    confirmar={() => void borrar(aBorrar)}
                    cancelar={() => setABorrar(null)}
                >
                    <p>
                        <span className="mam font-semibold text-tinta" lang="mam">
                            {aBorrar.mam}
                        </span>
                        {" · "}
                        {aBorrar.espanol}
                    </p>
                    <p>
                        Esto no se puede deshacer. Si solo querés que deje de salir en el sitio
                        público, quitale la revisión.
                    </p>
                </Confirmacion>
            )}
        </div>
    );
}
