/**
 * Los iconos de `lucide-react`, envueltos para fijar tamaño y trazo. Ninguno va solo:
 * cada uno acompaña a una palabra escrita, y por eso todos son `aria-hidden`.
 */

import {
    ArrowLeft,
    Ban,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    ExternalLink,
    KeyRound,
    type LucideIcon,
    Pencil,
    Plus,
    RotateCw,
    Trash2,
} from "lucide-react";

interface PropiedadesDeIcono {
    /** `size-3.5` en los botones de fila (letra de 12 px); `size-4` en los demás. */
    className?: string;
}

function envolver(Dibujo: LucideIcon) {
    return function Icono({ className = "size-4" }: PropiedadesDeIcono) {
        return (
            <Dibujo
                className={`shrink-0 ${className}`}
                strokeWidth={1.75}
                aria-hidden="true"
                focusable="false"
            />
        );
    };
}

/** Alta de una entrada, un tema, una fuente o una cuenta. */
export const IconoNueva = envolver(Plus);

export const IconoEditar = envolver(Pencil);

/** Borrar: la única acción del panel que no se puede deshacer. */
export const IconoBorrar = envolver(Trash2);

/** Cambiar la contraseña de una cuenta. */
export const IconoClave = envolver(KeyRound);

export const IconoActivar = envolver(CircleCheck);

export const IconoDesactivar = envolver(Ban);

/** Volver a consultar: «Actualizar» y «Reintentar». */
export const IconoActualizar = envolver(RotateCw);

export const IconoAnterior = envolver(ChevronLeft);

export const IconoSiguiente = envolver(ChevronRight);

/** Volver al listado desde un formulario. */
export const IconoVolver = envolver(ArrowLeft);

/** Sale del panel: la ficha de una fuente en el sitio de su institución. */
export const IconoAbrir = envolver(ExternalLink);
