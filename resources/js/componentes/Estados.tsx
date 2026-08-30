/**
 * Los cuatro estados que tiene cualquier pantalla que habla con el servidor: cargando, algo
 * salió mal, salió bien, o no hay nada que mostrar. Aquí una vez, para que se vean igual en
 * todo el panel y ninguna pantalla se olvide de tenerlas.
 *
 * `Cargando` y `Vacio` comparten alto y centrado a propósito: son los dos estados que ocupan
 * el hueco de una tabla, y si midieran distinto la tarjeta pegaría un salto al pasar de uno
 * al otro justo cuando el resultado aparece.
 *
 * El `role` de cada uno no es adorno. `alert` interrumpe al lector de pantalla y se reserva
 * para el error; `status` espera a que termine de leer lo que estaba leyendo, que es lo
 * correcto para «se guardó» y para «cargando».
 */

import type { ReactNode } from 'react';

export function Cargando({ texto = 'Cargando…' }: { texto?: string }) {
    return (
        <p className="px-6 py-12 text-center text-sm text-tinta-suave" role="status">
            {texto}
        </p>
    );
}

export function Aviso({ children, accion }: { children: ReactNode; accion?: ReactNode }) {
    return (
        <div className="tarjeta flex flex-wrap items-center justify-between gap-3 px-4 py-3" role="alert">
            <p className="text-sm text-alerta">{children}</p>
            {accion}
        </div>
    );
}

/**
 * «Se guardó», «quedaron revisadas», «se cambió la contraseña».
 *
 * `detalle` es para lo que hay que contar después de la buena noticia y no forma parte de
 * ella: que el normalizador corrigió la ortografía al guardar, o que cambiar la dirección de
 * un tema rompe el enlace viejo del sitio. Va en tinta suave porque explica, no celebra.
 */
export function Exito({ children, detalle }: { children: ReactNode; detalle?: ReactNode }) {
    return (
        <div className="tarjeta grid gap-1 px-4 py-3 text-sm" role="status">
            <p className="text-jade">{children}</p>
            {/* Truthiness y no `!== undefined`: quien llama suele pasar `condicion && <p/>`,
                que vale `false` cuando no hay detalle. Comprobar solo `undefined` dejaría un
                hueco vacío del tamaño del espaciado. */}
            {detalle ? <div className="text-tinta-suave">{detalle}</div> : null}
        </div>
    );
}

/**
 * No hay nada que mostrar.
 *
 * Una pantalla vacía es una invitación a hacer algo, no un renglón gris que informa de una
 * ausencia. Por eso admite un título —la frase corta que se lee de un vistazo— y una acción,
 * que es lo que evita que el editor tenga que ir a buscar el botón a otra parte de la
 * pantalla. `titulo` y `accion` son opcionales: «ninguna entrada coincide con estos filtros»
 * no invita a nada, solo constata.
 */
export function Vacio({
    titulo,
    children,
    accion,
}: {
    titulo?: string;
    children: ReactNode;
    accion?: ReactNode;
}) {
    return (
        <div className="grid justify-items-center gap-2 px-6 py-12 text-center">
            {titulo !== undefined && <p className="font-medium text-tinta">{titulo}</p>}
            <p className="max-w-prose text-sm text-tinta-suave">{children}</p>
            {accion !== undefined && <div className="mt-2">{accion}</div>}
        </div>
    );
}
