/**
 * Confirmar antes de hacer algo que no se puede deshacer.
 *
 * Sustituye a `window.confirm`, que bastaba mientras el aviso cabía en una frase y dejó de
 * bastar en cuanto hubo que explicar consecuencias. Tres cosas que el diálogo del sistema no
 * puede hacer y aquí importan:
 *
 * 1. **Decir qué hace el botón.** El del sistema siempre dice «Aceptar». Aquí dice «Borrar el
 *    tema», que es lo que va a pasar. Es la misma palabra que se pulsó para llegar hasta él.
 * 2. **Dar jerarquía al aviso.** «Las 8 entradas que tiene NO se borran» es la parte que hay
 *    que leer, y en un `confirm` va en el mismo párrafo plano que todo lo demás.
 * 3. **Distinguir borrar de desactivar.** Desactivar una cuenta se deshace; borrar un tema no.
 *    El botón va en rojo solo cuando de verdad no hay vuelta atrás.
 *
 * Es un `<dialog>` nativo y no un div flotante: de ahí salen gratis el atrapado del foco, el
 * cierre con Escape y el fondo inerte, que a mano son cien líneas y casi siempre quedan mal.
 *
 * El foco arranca en «Cancelar» a propósito. Quien llega aquí con el teclado y pulsa Intro
 * por inercia no debe borrar nada.
 *
 * Se monta y se desmonta con la acción pendiente —`{aBorrar !== null && <Confirmacion …>}`—,
 * así que abrir es montar. Por eso `showModal()` va en un efecto sin dependencias.
 */

import { useEffect, useRef, type ReactNode } from 'react';

export function Confirmacion({
    titulo,
    children,
    textoConfirmar,
    peligro = false,
    ocupado = false,
    confirmar,
    cancelar,
}: {
    titulo: string;
    /** Las consecuencias. Lo que el usuario tiene que saber antes de decidir. */
    children: ReactNode;
    /** Lo que hace el botón, con sus palabras: «Borrar el tema», no «Aceptar». */
    textoConfirmar: string;
    /** Solo para lo irreversible. Desactivar una cuenta no lo es. */
    peligro?: boolean;
    ocupado?: boolean;
    confirmar: () => void;
    cancelar: () => void;
}) {
    const dialogo = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        dialogo.current?.showModal();
    }, []);

    return (
        <dialog
            ref={dialogo}
            className="dialogo"
            aria-labelledby="confirmacion-titulo"
            // Escape. Se corta el cierre nativo y se avisa hacia arriba: quien manda sobre si
            // el diálogo existe es React, y dos fuentes de verdad sobre eso terminan en un
            // diálogo cerrado que sigue montado.
            onCancel={(evento) => {
                evento.preventDefault();
                cancelar();
            }}
            // Clic en el velo. El `<dialog>` nativo no lo cierra solo, y el velo es parte del
            // propio elemento: si el clic cayó en él y no en el contenido, el destino es el
            // diálogo mismo.
            onClick={(evento) => {
                if (evento.target === dialogo.current) {
                    cancelar();
                }
            }}
        >
            <h2 id="confirmacion-titulo" className="font-semibold">
                {titulo}
            </h2>

            <div className="mt-2 grid gap-2 text-sm text-tinta-suave">{children}</div>

            <div className="mt-5 flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    className="boton-secundario"
                    autoFocus
                    onClick={cancelar}
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    className={peligro ? 'boton-peligro' : 'boton'}
                    disabled={ocupado}
                    onClick={confirmar}
                >
                    {textoConfirmar}
                </button>
            </div>
        </dialog>
    );
}
