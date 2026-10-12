/**
 * Temas del vocabulario.
 *
 * El panel dice «tema» donde la API dice «categoría»: es la palabra que ya usan el
 * formulario de entradas, la exportación (`temas`) y el sitio público. El código conserva el
 * nombre del backend para que un archivo y su controlador se sigan llamando igual.
 *
 * Sin paginar y sin buscador: son decenas, no miles, y el orden es el que decidió el
 * docente. Ordenarlos aquí por nombre rompería esa secuencia, que es pedagógica.
 */

import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router";

import { borrarCategoria, listarCategorias } from "../api/recursos";
import { Confirmacion } from "../componentes/Confirmacion";
import { Aviso, Cargando, Vacio } from "../componentes/Estados";
import {
    IconoActualizar,
    IconoBorrar,
    IconoEditar,
    IconoNueva,
} from "../componentes/Iconos";
import { usePanel } from "../panel";
import { ANCHO_TABLA, useCabeLaTabla } from "../pantalla";
import type { Categoria } from "../tipos";

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

export function ListaCategorias() {
    const { sesion, recargarCatalogos } = usePanel();
    const cabeLaTabla = useCabeLaTabla(ANCHO_TABLA.temas);

    const [temas, setTemas] = useState<Categoria[] | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [ocupado, setOcupado] = useState<number | null>(null);
    const [aBorrar, setABorrar] = useState<Categoria | null>(null);

    const cargar = useCallback(async (): Promise<void> => {
        setCargando(true);

        try {
            setTemas(await listarCategorias());
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

    async function borrar(tema: Categoria): Promise<void> {
        setABorrar(null);
        setOcupado(tema.id);
        setError(null);

        try {
            await borrarCategoria(tema.id);
            await cargar();
            // El selector de temas del formulario de entradas se quedó con la lista vieja.
            await recargarCatalogos();
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setOcupado(null);
        }
    }

    const nombreDelPadre = (tema: Categoria): string =>
        tema.padre_id === null
            ? "—"
            : (temas?.find((otro) => otro.id === tema.padre_id)?.nombre_es ?? "—");

    function Acciones({ tema }: { tema: Categoria }) {
        return (
            <div className="acciones-fila">
                <Link to={`/categorias/${tema.id}`} className="boton-fila">
                    <IconoEditar className="size-3.5" />
                    Editar
                </Link>

                {sesion.es_administrador && (
                    <button
                        type="button"
                        className="boton-fila-peligro"
                        disabled={ocupado === tema.id}
                        onClick={() => setABorrar(tema)}
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
                    <h1 className="text-xl font-semibold">Temas</h1>
                    <p className="mt-1 text-sm text-tinta-suave">
                        Agrupan el vocabulario y son la puerta de entrada del sitio público. El
                        orden lo decidís vos, no el alfabeto.
                    </p>
                </div>

                <Link to="/categorias/nuevo" className="boton">
                    <IconoNueva />
                    Nuevo tema
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
                {cargando && temas === null ? (
                    <Cargando />
                ) : temas === null || temas.length === 0 ? (
                    <Vacio
                        titulo="Todavía no hay temas"
                        accion={
                            <Link to="/categorias/nuevo" className="boton">
                                <IconoNueva />
                                Crear el primero
                            </Link>
                        }
                    >
                        Sin temas, el sitio público no tiene por dónde empezar: son su portada.
                    </Vacio>
                ) : cabeLaTabla ? (
                    <table className="tabla">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>En Mam</th>
                                <th>Dirección</th>
                                <th>Dentro de</th>
                                <th>Orden</th>
                                <th>Entradas</th>
                                <th className="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {temas.map((tema) => (
                                <tr key={tema.id}>
                                    <td>
                                        <Link
                                            to={`/categorias/${tema.id}`}
                                            className="enlace font-medium"
                                        >
                                            {tema.icono !== null && `${tema.icono} `}
                                            {tema.nombre_es}
                                        </Link>
                                    </td>
                                    {/* Es texto en Mam y pasa por el normalizador igual que un
                                        lema, así que se ve con los mismos trazos. */}
                                    <td className="mam" lang="mam">{tema.nombre_mam ?? "—"}</td>
                                    <td className="font-mono text-xs text-tinta-suave">
                                        {tema.slug}
                                    </td>
                                    <td className="text-tinta-suave">
                                        {nombreDelPadre(tema)}
                                    </td>
                                    <td className="text-tinta-suave">{tema.orden}</td>
                                    <td className="text-tinta-suave">
                                        {tema.total_entradas ?? 0}
                                    </td>
                                    <td className="text-right whitespace-nowrap">
                                        <Acciones tema={tema} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <ul className="lista-fichas">
                        {temas.map((tema) => (
                            <li key={tema.id} className="ficha">
                                <div className="flex items-start justify-between gap-3">
                                    <Link
                                        to={`/categorias/${tema.id}`}
                                        className="enlace font-medium"
                                    >
                                        {tema.icono !== null && `${tema.icono} `}
                                        {tema.nombre_es}
                                    </Link>

                                    <span className="shrink-0 text-xs text-tinta-suave">
                                        {tema.total_entradas ?? 0} entrada(s)
                                    </span>
                                </div>

                                {tema.nombre_mam !== null && (
                                    <p className="mam text-sm" lang="mam">{tema.nombre_mam}</p>
                                )}

                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-tinta-suave">
                                    <span className="font-mono">{tema.slug}</span>
                                    <span>orden {tema.orden}</span>
                                    {tema.padre_id !== null && (
                                        <span>dentro de {nombreDelPadre(tema)}</span>
                                    )}
                                </div>

                                <div className="border-t border-borde pt-2 whitespace-nowrap">
                                    <Acciones tema={tema} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {aBorrar !== null && (
                <Confirmacion
                    titulo={`¿Borrar el tema «${aBorrar.nombre_es}»?`}
                    textoConfirmar="Borrar el tema"
                    peligro
                    ocupado={ocupado === aBorrar.id}
                    confirmar={() => void borrar(aBorrar)}
                    cancelar={() => setABorrar(null)}
                >
                    <p>
                        Las {aBorrar.total_entradas ?? 0} entrada(s) que tiene{" "}
                        <strong className="font-semibold text-tinta">no se borran</strong>:
                        pierden esta clasificación y nada más.
                    </p>
                    <p>Los temas que cuelguen de él quedan sueltos.</p>
                </Confirmacion>
            )}
        </div>
    );
}
