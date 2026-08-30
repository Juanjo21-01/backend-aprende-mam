/**
 * Los tres estados que tiene cualquier pantalla que habla con el servidor: cargando, algo
 * salió mal, o no hay nada que mostrar. Aquí una vez, para que las tres se vean igual en
 * todo el panel y ninguna se olvide de tenerlas.
 */

import type { ReactNode } from 'react';

export function Cargando({ texto = 'Cargando…' }: { texto?: string }) {
    return (
        <p className="py-10 text-center text-sm text-tenue" role="status">
            {texto}
        </p>
    );
}

export function Aviso({
    tono = 'error',
    children,
    accion,
}: {
    tono?: 'error' | 'neutro';
    children: ReactNode;
    accion?: ReactNode;
}) {
    const color = tono === 'error' ? 'text-error' : 'text-tenue';

    return (
        <div className="tarjeta flex flex-wrap items-center justify-between gap-3 px-4 py-3" role="alert">
            <p className={`text-sm ${color}`}>{children}</p>
            {accion}
        </div>
    );
}

export function Vacio({ children }: { children: ReactNode }) {
    return <p className="py-10 text-center text-sm text-tenue">{children}</p>;
}
