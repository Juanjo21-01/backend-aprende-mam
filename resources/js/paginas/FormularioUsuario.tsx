/**
 * Alta y edición de una cuenta del panel.
 *
 * La contraseña solo viaja al crear. Al editar, el backend responde `prohibited` si llega:
 * cambiarla es otra acción, con su propia política, y además cierra las sesiones abiertas de
 * esa persona. Está en la lista de cuentas.
 *
 * Ojo con la asimetría de nombres: la respuesta viene en castellano (`nombre`, `correo`) y
 * el formulario se manda en inglés (`name`, `email`), que es como se llaman las columnas de
 * `users`. Confundirlas da un 422 sin pistas.
 */

import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";

import { ErrorApi } from "../api/cliente";
import {
    actualizarUsuario,
    crearUsuario,
    obtenerUsuario,
} from "../api/recursos";
import { Aviso, Cargando } from "../componentes/Estados";
import type { DatosUsuario, Rol, Usuario } from "../tipos";

interface Formulario {
    name: string;
    email: string;
    rol: Rol;
    password: string;
    password_confirmation: string;
}

const VACIO: Formulario = {
    name: "",
    email: "",
    rol: "editor",
    password: "",
    password_confirmation: "",
};

export function FormularioUsuario() {
    const { id } = useParams();
    const navegar = useNavigate();

    const esAlta = id === undefined;

    const [formulario, setFormulario] = useState<Formulario>(VACIO);
    const [usuario, setUsuario] = useState<Usuario | null>(null);
    const [cargando, setCargando] = useState(!esAlta);
    const [guardando, setGuardando] = useState(false);
    const [fallo, setFallo] = useState<ErrorApi | Error | null>(null);

    useEffect(() => {
        if (esAlta) {
            setFormulario(VACIO);
            setUsuario(null);
            setCargando(false);

            return;
        }

        let cancelado = false;
        setCargando(true);

        obtenerUsuario(Number(id))
            .then((cuenta) => {
                if (cancelado) {
                    return;
                }

                setUsuario(cuenta);
                setFormulario({
                    name: cuenta.nombre,
                    email: cuenta.correo,
                    rol: cuenta.rol,
                    password: "",
                    password_confirmation: "",
                });
                setFallo(null);
            })
            .catch((error: unknown) => {
                if (!cancelado) {
                    setFallo(
                        error instanceof Error
                            ? error
                            : new Error("No se pudo cargar la cuenta."),
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
            const datos: DatosUsuario = {
                name: formulario.name,
                email: formulario.email,
                rol: formulario.rol,
            };

            if (esAlta) {
                datos.password = formulario.password;
                datos.password_confirmation = formulario.password_confirmation;

                await crearUsuario(datos);
            } else {
                await actualizarUsuario(Number(id), datos);
            }

            navegar("/usuarios");
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

    /**
     * Las dos formas de dejar el panel sin quien lo administre. El backend las rechaza con
     * un 422 explicado; aquí el selector se bloquea con el motivo escrito, que es mejor que
     * ofrecer una opción que va a fallar.
     */
    const rolBloqueado =
        usuario === null
            ? null
            : usuario.es_uno_mismo
              ? "No podés cambiarte el rol a vos mismo."
              : usuario.es_el_ultimo_administrador
                ? "Es el único administrador activo. Nombrá a otro antes de bajarle el rol."
                : null;

    if (cargando) {
        return <Cargando texto="Cargando la cuenta…" />;
    }

    return (
        <div className="grid max-w-2xl gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold">
                    {esAlta ? "Nueva cuenta" : "Editar cuenta"}
                </h1>

                <Link
                    to="/usuarios"
                    className="text-sm text-tinta-suave underline-offset-2 hover:underline"
                >
                    Volver a las cuentas
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
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="etiqueta" htmlFor="name">
                            Nombre
                        </label>
                        <input
                            id="name"
                            className="campo"
                            value={formulario.name}
                            onChange={(evento) => cambiar("name", evento.target.value)}
                            required
                            autoFocus
                            autoComplete="off"
                        />
                        {errorDe("name") !== undefined && (
                            <p className="mt-1 text-xs text-alerta">{errorDe("name")}</p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="email">
                            Correo
                        </label>
                        <input
                            id="email"
                            className="campo"
                            type="email"
                            value={formulario.email}
                            onChange={(evento) => cambiar("email", evento.target.value)}
                            required
                            autoComplete="off"
                        />
                        <p className="mt-1 text-xs text-tinta-suave">Con esto entra al panel.</p>
                        {errorDe("email") !== undefined && (
                            <p className="mt-1 text-xs text-alerta">{errorDe("email")}</p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="etiqueta" htmlFor="rol">
                        Rol
                    </label>
                    <select
                        id="rol"
                        className="campo sm:w-64"
                        value={formulario.rol}
                        disabled={rolBloqueado !== null}
                        onChange={(evento) =>
                            cambiar("rol", evento.target.value as Rol)
                        }
                    >
                        <option value="editor">Editor</option>
                        <option value="administrador">Administrador</option>
                    </select>

                    <p className="mt-1 text-xs text-tinta-suave">
                        {rolBloqueado ??
                            "El editor carga y corrige. El administrador además borra y firma las revisiones."}
                    </p>
                    {errorDe("rol") !== undefined && (
                        <p className="mt-1 text-xs text-alerta">{errorDe("rol")}</p>
                    )}
                </div>

                {esAlta && (
                    <div className="grid gap-4 border-t border-borde pt-4 sm:grid-cols-2">
                        <div>
                            <label className="etiqueta" htmlFor="password">
                                Contraseña
                            </label>
                            <input
                                id="password"
                                className="campo"
                                type="password"
                                value={formulario.password}
                                onChange={(evento) =>
                                    cambiar("password", evento.target.value)
                                }
                                required
                                minLength={8}
                                autoComplete="new-password"
                            />
                            <p className="mt-1 text-xs text-tinta-suave">
                                Mínimo 8 caracteres. Decísela en persona: el sistema no manda
                                correos.
                            </p>
                            {errorDe("password") !== undefined && (
                                <p className="mt-1 text-xs text-alerta">
                                    {errorDe("password")}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="etiqueta" htmlFor="password_confirmation">
                                Repetirla
                            </label>
                            <input
                                id="password_confirmation"
                                className="campo"
                                type="password"
                                value={formulario.password_confirmation}
                                onChange={(evento) =>
                                    cambiar("password_confirmation", evento.target.value)
                                }
                                required
                                autoComplete="new-password"
                            />
                        </div>
                    </div>
                )}

                {!esAlta && (
                    <p className="border-t border-borde pt-4 text-xs text-tinta-suave">
                        La contraseña se cambia desde la lista de cuentas: es otra acción,
                        porque además cierra las sesiones abiertas de esa persona.
                    </p>
                )}

                <div className="flex flex-wrap items-center gap-3 border-t border-borde pt-4">
                    <button type="submit" className="boton" disabled={guardando}>
                        {guardando ? "Guardando…" : "Guardar"}
                    </button>

                    <Link
                        to="/usuarios"
                        className="text-sm text-tinta-suave underline-offset-2 hover:underline"
                    >
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    );
}
