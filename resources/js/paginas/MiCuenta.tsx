/**
 * La cuenta propia: cambiar la contraseña.
 *
 * Está fuera de la pantalla de cuentas a propósito. Aquella es solo para administradores, y
 * un editor también tiene que poder cambiar la suya. La ruta del backend tampoco lleva
 * identificador: opera siempre sobre quien entró y sobre nadie más.
 *
 * Pide la contraseña actual, al revés que el reseteo del administrador. El motivo es
 * concreto: un panel abierto y desatendido —la computadora de la escuela— no puede servir
 * para cambiarle la clave a su dueño y dejarlo fuera.
 */

import { useState } from "react";

import { ErrorApi } from "../api/cliente";
import { cambiarContrasenaPropia } from "../api/recursos";
import { Aviso } from "../componentes/Estados";
import { usePanel } from "../panel";

export function MiCuenta() {
    const { sesion } = usePanel();

    const [actual, setActual] = useState("");
    const [nueva, setNueva] = useState("");
    const [confirmacion, setConfirmacion] = useState("");
    const [guardando, setGuardando] = useState(false);
    const [fallo, setFallo] = useState<ErrorApi | Error | null>(null);
    const [hecho, setHecho] = useState<string | null>(null);

    async function guardar(): Promise<void> {
        setGuardando(true);
        setFallo(null);
        setHecho(null);

        try {
            const respuesta = await cambiarContrasenaPropia(
                actual,
                nueva,
                confirmacion,
            );

            setHecho(respuesta.mensaje);
            setActual("");
            setNueva("");
            setConfirmacion("");
        } catch (error) {
            setFallo(
                error instanceof Error
                    ? error
                    : new Error("No se pudo cambiar la contraseña."),
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

    return (
        <div className="grid max-w-xl gap-5">
            <div>
                <h1 className="text-xl font-semibold">Mi cuenta</h1>
                <p className="mt-1 text-sm text-tinta-suave">
                    {sesion.nombre} · {sesion.correo} · {sesion.rol_nombre}
                </p>
            </div>

            {errorGeneral !== null && <Aviso>{errorGeneral}</Aviso>}

            {hecho !== null && (
                <div
                    className="tarjeta px-4 py-3 text-sm text-jade"
                    role="status"
                >
                    {hecho} Esta sesión sigue abierta.
                </div>
            )}

            <form
                className="tarjeta grid gap-4 p-5"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    void guardar();
                }}
            >
                <div>
                    <label className="etiqueta" htmlFor="actual">
                        Contraseña actual
                    </label>
                    <input
                        id="actual"
                        className="campo"
                        type="password"
                        value={actual}
                        onChange={(evento) => setActual(evento.target.value)}
                        required
                        autoComplete="current-password"
                    />
                    {errorDe("current_password") !== undefined && (
                        <p className="mt-1 text-xs text-alerta">
                            {errorDe("current_password")}
                        </p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="etiqueta" htmlFor="nueva">
                            Contraseña nueva
                        </label>
                        <input
                            id="nueva"
                            className="campo"
                            type="password"
                            value={nueva}
                            onChange={(evento) => setNueva(evento.target.value)}
                            required
                            minLength={8}
                            autoComplete="new-password"
                        />
                        <p className="mt-1 text-xs text-tinta-suave">
                            Mínimo 8 caracteres.
                        </p>
                        {errorDe("password") !== undefined && (
                            <p className="mt-1 text-xs text-alerta">
                                {errorDe("password")}
                            </p>
                        )}
                    </div>

                    <div>
                        <label
                            className="etiqueta"
                            htmlFor="confirmacion-propia"
                        >
                            Repetirla
                        </label>
                        <input
                            id="confirmacion-propia"
                            className="campo"
                            type="password"
                            value={confirmacion}
                            onChange={(evento) =>
                                setConfirmacion(evento.target.value)
                            }
                            required
                            autoComplete="new-password"
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3 border-t border-borde pt-4">
                    <button
                        type="submit"
                        className="boton"
                        disabled={guardando}
                    >
                        {guardando ? "Guardando…" : "Cambiar la contraseña"}
                    </button>

                    <p className="text-xs text-tinta-suave">
                        Si la olvidás, te la resetea un administrador: este
                        sistema no manda correos de recuperación.
                    </p>
                </div>
            </form>
        </div>
    );
}
