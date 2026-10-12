/**
 * Cuentas del panel. Solo administradores; lo impone `UserPolicy` en el servidor.
 *
 * Tres cosas que esta pantalla no ofrece, y ninguna es un olvido:
 *
 * - **Borrar.** La API no tiene `destroy`: la baja de una cuenta es desactivarla. Una cuenta
 *   borrada no vuelve y se lleva el rastro de quién trabajó en el contenido.
 * - **Desactivarse a uno mismo, o al último administrador activo.** Son las dos formas de
 *   quedarse fuera del propio panel para siempre. El backend las rechaza con un 422
 *   explicado; aquí el botón ni siquiera se ofrece, con el motivo a la vista.
 * - **Recuperar la contraseña por correo.** No existe en este sistema, y es deliberado: con
 *   tres o cuatro cuentas resulta más fiable que un administrador la resetee desde acá que
 *   depender del SMTP de un alojamiento compartido. Para el administrador único que se queda
 *   fuera está `php artisan mam:cambiar-contrasena`.
 */

import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router";

import { ErrorApi } from "../api/cliente";
import {
    cambiarEstadoUsuario,
    listarUsuarios,
    resetearContrasena,
} from "../api/recursos";
import { Confirmacion } from "../componentes/Confirmacion";
import { Aviso, Cargando, Exito } from "../componentes/Estados";
import {
    IconoActivar,
    IconoClave,
    IconoDesactivar,
    IconoEditar,
    IconoNueva,
} from "../componentes/Iconos";
import { ANCHO_TABLA, useCabeLaTabla } from "../pantalla";
import type { Usuario } from "../tipos";

interface Reseteo {
    usuario: Usuario;
    password: string;
    confirmacion: string;
}

function mensajeDe(fallo: unknown): string {
    return fallo instanceof Error ? fallo.message : "Algo salió mal.";
}

/** Por qué no se puede dar de baja esta cuenta, o `null` si sí se puede. */
function motivoParaNoDesactivar(usuario: Usuario): string | null {
    // Fragmentos y no frases: se leen dentro de «No se desactiva: …», donde el motivo es la
    // parte que hay que ver. Antes vivían en un `title` y en táctil no había forma de sacarlos.
    if (usuario.es_uno_mismo) {
        return "es tu propia cuenta";
    }

    if (usuario.es_el_ultimo_administrador) {
        return "es el único administrador activo";
    }

    return null;
}

export function ListaUsuarios() {
    const cabeLaTabla = useCabeLaTabla(ANCHO_TABLA.cuentas);

    const [usuarios, setUsuarios] = useState<Usuario[] | null>(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [ocupada, setOcupada] = useState<number | null>(null);
    const [reseteo, setReseteo] = useState<Reseteo | null>(null);
    const [hecho, setHecho] = useState<string | null>(null);
    const [aDesactivar, setADesactivar] = useState<Usuario | null>(null);

    const cargar = useCallback(async (): Promise<void> => {
        setCargando(true);

        try {
            setUsuarios(await listarUsuarios());
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

    async function alternarEstado(usuario: Usuario): Promise<void> {
        setADesactivar(null);
        setOcupada(usuario.id);
        setError(null);
        setHecho(null);

        try {
            await cambiarEstadoUsuario(usuario.id, !usuario.activo);
            await cargar();
            setHecho(
                usuario.activo
                    ? `Se desactivó la cuenta de ${usuario.nombre}.`
                    : `Se activó la cuenta de ${usuario.nombre}.`,
            );
        } catch (fallo) {
            setError(mensajeDe(fallo));
        } finally {
            setOcupada(null);
        }
    }

    async function guardarContrasena(): Promise<void> {
        if (reseteo === null) {
            return;
        }

        setOcupada(reseteo.usuario.id);
        setError(null);
        setHecho(null);

        try {
            await resetearContrasena(
                reseteo.usuario.id,
                reseteo.password,
                reseteo.confirmacion,
            );

            setHecho(
                `Contraseña nueva para ${reseteo.usuario.nombre}. Se cerraron sus sesiones ` +
                    `abiertas, así que tiene que entrar otra vez.`,
            );
            setReseteo(null);
        } catch (fallo) {
            setError(
                fallo instanceof ErrorApi
                    ? (fallo.campo("password") ?? fallo.message)
                    : mensajeDe(fallo),
            );
        } finally {
            setOcupada(null);
        }
    }

    function Estado({ usuario }: { usuario: Usuario }) {
        return (
            <span
                className={`text-xs ${usuario.activo ? "text-jade" : "text-tinta-suave"}`}
            >
                {usuario.activo ? "Activa" : "Desactivada"}
            </span>
        );
    }

    function Acciones({ usuario }: { usuario: Usuario }) {
        const motivo = motivoParaNoDesactivar(usuario);

        return (
            <div className="acciones-fila">
                <Link to={`/usuarios/${usuario.id}`} className="boton-fila">
                    <IconoEditar className="size-3.5" />
                    Editar
                </Link>

                <button
                    type="button"
                    className="boton-fila"
                    disabled={ocupada === usuario.id}
                    onClick={() =>
                        setReseteo({ usuario, password: "", confirmacion: "" })
                    }
                >
                    <IconoClave className="size-3.5" />
                    Contraseña
                </button>

                {motivo === null ? (
                    <button
                        type="button"
                        className="boton-fila"
                        disabled={ocupada === usuario.id}
                        onClick={() =>
                            usuario.activo
                                ? setADesactivar(usuario)
                                : void alternarEstado(usuario)
                        }
                    >
                        {usuario.activo ? (
                            <IconoDesactivar className="size-3.5" />
                        ) : (
                            <IconoActivar className="size-3.5" />
                        )}
                        {usuario.activo ? "Desactivar" : "Activar"}
                    </button>
                ) : (
                    // Activar de vuelta no se confirma: no rompe nada y es la salida del
                    // error. Solo desactivar pregunta.
                    <span className="py-1 text-xs text-tinta-suave">
                        No se desactiva: {motivo}
                    </span>
                )}
            </div>
        );
    }

    return (
        <div className="grid gap-5">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Cuentas</h1>
                    <p className="mt-1 text-sm text-tinta-suave">
                        El editor carga y corrige contenido. El administrador además borra y
                        firma las revisiones.
                    </p>
                </div>

                <Link to="/usuarios/nueva" className="boton">
                    <IconoNueva />
                    Nueva cuenta
                </Link>
            </div>

            {error !== null && <Aviso>{error}</Aviso>}

            {hecho !== null && <Exito>{hecho}</Exito>}

            <div className="tarjeta overflow-x-auto">
                {cargando && usuarios === null ? (
                    <Cargando />
                ) : cabeLaTabla ? (
                    <table className="tabla">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th className="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(usuarios ?? []).map((usuario) => (
                                <tr key={usuario.id}>
                                    <td>
                                        <Link
                                            to={`/usuarios/${usuario.id}`}
                                            className="enlace font-medium"
                                        >
                                            {usuario.nombre}
                                        </Link>
                                        {usuario.es_uno_mismo && (
                                            <span className="chip-nota ml-2">vos</span>
                                        )}
                                    </td>
                                    <td className="text-tinta-suave">{usuario.correo}</td>
                                    <td>{usuario.rol_nombre}</td>
                                    <td>
                                        <Estado usuario={usuario} />
                                    </td>
                                    <td className="text-right whitespace-nowrap">
                                        <Acciones usuario={usuario} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <ul className="lista-fichas">
                        {(usuarios ?? []).map((usuario) => (
                            <li key={usuario.id} className="ficha">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <Link
                                            to={`/usuarios/${usuario.id}`}
                                            className="enlace font-medium"
                                        >
                                            {usuario.nombre}
                                        </Link>
                                        {usuario.es_uno_mismo && (
                                            <span className="chip-nota ml-2">vos</span>
                                        )}
                                        <p className="text-sm break-all text-tinta-suave">
                                            {usuario.correo}
                                        </p>
                                    </div>

                                    <span className="shrink-0 text-xs text-tinta-suave">
                                        {usuario.rol_nombre}
                                    </span>
                                </div>

                                <Estado usuario={usuario} />

                                <div className="border-t border-borde pt-2 whitespace-nowrap">
                                    <Acciones usuario={usuario} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {reseteo !== null && (
                <form
                    className="tarjeta grid max-w-xl gap-4 p-5"
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        void guardarContrasena();
                    }}
                >
                    <div>
                        <h2 className="font-semibold">
                            Contraseña nueva para {reseteo.usuario.nombre}
                        </h2>
                        <p className="mt-1 text-sm text-tinta-suave">
                            No hace falta la anterior: quien la olvidó no la sabe. Al
                            guardarla se cierran sus sesiones abiertas.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="etiqueta" htmlFor="password">
                                Contraseña
                            </label>
                            <input
                                id="password"
                                className="campo"
                                type="password"
                                value={reseteo.password}
                                onChange={(evento) =>
                                    setReseteo({
                                        ...reseteo,
                                        password: evento.target.value,
                                    })
                                }
                                required
                                minLength={8}
                                autoComplete="new-password"
                            />
                            <p className="ayuda">Mínimo 8 caracteres.</p>
                        </div>

                        <div>
                            <label className="etiqueta" htmlFor="confirmacion">
                                Repetirla
                            </label>
                            <input
                                id="confirmacion"
                                className="campo"
                                type="password"
                                value={reseteo.confirmacion}
                                onChange={(evento) =>
                                    setReseteo({
                                        ...reseteo,
                                        confirmacion: evento.target.value,
                                    })
                                }
                                required
                                autoComplete="new-password"
                            />
                        </div>
                    </div>

                    <div className="pie-formulario">
                        <button
                            type="submit"
                            className="boton"
                            disabled={ocupada === reseteo.usuario.id}
                        >
                            Cambiar la contraseña
                        </button>

                        <button
                            type="button"
                            className="enlace text-sm text-tinta-suave"
                            onClick={() => setReseteo(null)}
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            )}

            {aDesactivar !== null && (
                <Confirmacion
                    titulo={`¿Desactivar la cuenta de ${aDesactivar.nombre}?`}
                    textoConfirmar="Desactivar la cuenta"
                    ocupado={ocupada === aDesactivar.id}
                    confirmar={() => void alternarEstado(aDesactivar)}
                    cancelar={() => setADesactivar(null)}
                >
                    <p>
                        No se borra nada: deja de poder entrar y se cierran sus sesiones
                        abiertas.
                    </p>
                    <p>Se puede volver a activar cuando haga falta.</p>
                </Confirmacion>
            )}
        </div>
    );
}
