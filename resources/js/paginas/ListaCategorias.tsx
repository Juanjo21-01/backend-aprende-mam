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
import { Aviso, Cargando, Vacio } from "../componentes/Estados";
import { usePanel } from "../panel";
import type { Categoria } from "../tipos";

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

export function ListaCategorias() {
    const { sesion, recargarCatalogos } = usePanel();

    const [temas, setTemas] = useState<Categoria[] | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [ocupado, setOcupado] = useState<number | null>(null);

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
        const aviso =
            `¿Borrar el tema «${tema.nombre_es}»?\n\n` +
            `Las ${tema.total_entradas ?? 0} entrada(s) que tiene NO se borran: pierden esta ` +
            `clasificación y nada más. Los temas que cuelguen de él quedan sueltos.`;

        if (!window.confirm(aviso)) {
            return;
        }

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
                    <Vacio>Todavía no hay temas.</Vacio>
                ) : (
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
                                <tr
                                    key={tema.id}
                                    className="border-b border-borde last:border-0"
                                >
                                    <td>
                                        <Link
                                            to={`/categorias/${tema.id}`}
                                            className="enlace font-medium"
                                        >
                                            {tema.icono !== null && `${tema.icono} `}
                                            {tema.nombre_es}
                                        </Link>
                                    </td>
                                    <td>{tema.nombre_mam ?? "—"}</td>
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
                                        <Link
                                            to={`/categorias/${tema.id}`}
                                            className="enlace text-xs"
                                        >
                                            Editar
                                        </Link>

                                        {sesion.es_administrador && (
                                            <button
                                                type="button"
                                                className="enlace-peligro ml-3"
                                                disabled={ocupado === tema.id}
                                                onClick={() => void borrar(tema)}
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
