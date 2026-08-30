/**
 * Lo que el panel necesita saber antes de dibujar nada: quién entró y los tres catálogos.
 *
 * Se piden una sola vez al arrancar y no en cada pantalla. Son 7 temas, 2 fuentes y 16
 * clases de palabra: cabe entero en memoria y evita que el formulario de entradas espere
 * tres peticiones cada vez que se abre —que, cargando vocabulario en serie, es cada
 * palabra.
 *
 * La sesión decide qué botones se ofrecen, no qué se permite: quien manda son las políticas
 * del servidor. Ofrecer «borrar» a un editor solo produce un 403 con su motivo.
 */

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';

import { listarCategorias, listarClasesDePalabra, listarFuentes, sesionActual } from './api/recursos';
import { Aviso, Cargando } from './componentes/Estados';
import type { Categoria, CategoriaGramatical, Fuente, Sesion } from './tipos';

interface Contexto {
    sesion: Sesion;
    categorias: Categoria[];
    fuentes: Fuente[];
    clases: CategoriaGramatical[];
    /** Para cuando el panel edite temas o fuentes y los selectores queden viejos. */
    recargarCatalogos: () => Promise<void>;
}

const ContextoPanel = createContext<Contexto | null>(null);

export function usePanel(): Contexto {
    const contexto = useContext(ContextoPanel);

    if (contexto === null) {
        throw new Error('usePanel() fuera de <ProveedorPanel>.');
    }

    return contexto;
}

export function ProveedorPanel({ children }: { children: ReactNode }) {
    const [datos, setDatos] = useState<Omit<Contexto, 'recargarCatalogos'> | null>(null);
    const [error, setError] = useState<string | null>(null);

    const cargar = useCallback(async (): Promise<void> => {
        setError(null);

        try {
            const [sesion, categorias, fuentes, clases] = await Promise.all([
                sesionActual(),
                listarCategorias(),
                listarFuentes(),
                listarClasesDePalabra(),
            ]);

            setDatos({ sesion, categorias, fuentes, clases });
        } catch (fallo) {
            // Un 401 ya mandó al login desde el cliente; lo que llega aquí es otra cosa.
            setError(fallo instanceof Error ? fallo.message : 'No se pudo cargar el panel.');
        }
    }, []);

    const recargarCatalogos = useCallback(async (): Promise<void> => {
        const [categorias, fuentes] = await Promise.all([listarCategorias(), listarFuentes()]);

        setDatos((previo) => (previo === null ? previo : { ...previo, categorias, fuentes }));
    }, []);

    useEffect(() => {
        void cargar();
    }, [cargar]);

    if (error !== null) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-10">
                <Aviso
                    accion={
                        <button type="button" className="boton-secundario" onClick={() => void cargar()}>
                            Reintentar
                        </button>
                    }
                >
                    {error}
                </Aviso>
            </div>
        );
    }

    if (datos === null) {
        return <Cargando texto="Abriendo el panel…" />;
    }

    return (
        <ContextoPanel.Provider value={{ ...datos, recargarCatalogos }}>{children}</ContextoPanel.Provider>
    );
}
