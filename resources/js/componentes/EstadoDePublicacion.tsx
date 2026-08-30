/**
 * «Lo que guardé, ¿ya está en el sitio?», en una franja bajo el encabezado.
 *
 * Es la única pregunta del panel que no se contesta mirando contenido. Antes no se
 * contestaba en ningún lado: había que entrar al servidor a leer el log.
 *
 * Se refresca al cambiar de pantalla —que es justo después de guardar algo— y cada minuto,
 * porque la publicación llega sola cinco minutos más tarde y el docente no debería tener que
 * recargar para enterarse. Un botón la actualiza a mano.
 *
 * EL DETALLE ERA UN `title` Y AHORA ES TEXTO. Un tooltip no existe en una pantalla táctil:
 * en el teléfono, la frase que explica por qué no hay trabajador de cola corriendo era
 * literalmente inalcanzable. Va dentro de un `<details>` nativo —sin JavaScript, navegable
 * con teclado— que deja siempre visible el renglón corto y guarda debajo la explicación.
 *
 * Tres colores para cinco estados, y a propósito: jade lo que está bien, alerta lo que está
 * roto, gris lo que está esperando. La frase distingue el resto. El oro no aparece aquí:
 * está reservado a la revisión de una entrada.
 *
 * Si la consulta falla no se pinta nada. Es información de apoyo: romper el encabezado por
 * ella sería peor que no mostrarla.
 */

import { useCallback, useEffect, useState } from "react";
import { useLocation } from "react-router";

import { estadoDePublicacion } from "../api/recursos";
import type { EstadoPublicacion } from "../tipos";

const UN_MINUTO = 60_000;

const relativo = new Intl.RelativeTimeFormat("es", { numeric: "auto" });

/** «hace un momento», «hace 3 minutos», «hace 2 horas». */
function haceCuanto(iso: string): string {
    const minutos = Math.round((Date.parse(iso) - Date.now()) / 60_000);

    // `Intl` con `numeric: "auto"` traduce el cero como «este minuto», que leído dentro de
    // «Última publicación: versión 27, este minuto» suena a error de redacción. Y es
    // justamente el caso que más se ve: el de recién publicar.
    if (minutos === 0) {
        return "hace un momento";
    }

    if (Math.abs(minutos) < 60) {
        return relativo.format(minutos, "minute");
    }

    if (Math.abs(minutos) < 60 * 24) {
        return relativo.format(Math.round(minutos / 60), "hour");
    }

    return relativo.format(Math.round(minutos / (60 * 24)), "day");
}

function aLaHora(iso: string): string {
    return new Date(iso).toLocaleTimeString("es", {
        hour: "2-digit",
        minute: "2-digit",
    });
}

interface Aspecto {
    color: string;
    texto: string;
    detalle: string;
}

function describir(estado: EstadoPublicacion): Aspecto {
    const contenido = `El contenido va por la versión ${estado.version}.`;

    const ultima =
        estado.version_publicada === null || estado.publicado_en === null
            ? "Todavía no se ha publicado ninguna versión."
            : `Última publicación: versión ${estado.version_publicada}, ${haceCuanto(estado.publicado_en)}.`;

    // Sin deploy hook no hay a quién avisar. Decirlo es mejor que dejar «pendiente» para
    // siempre, que parecería una publicación que tarda en llegar.
    if (!estado.habilitada) {
        return {
            color: "bg-tinta-suave",
            texto: "Publicación sin configurar",
            detalle: `${contenido} ${ultima} No hay un destino de publicación configurado, así que el sitio no se va a recompilar solo.`,
        };
    }

    if (!estado.sin_publicar) {
        return {
            color: "bg-jade",
            texto: `Sitio al día · v${estado.version}`,
            detalle: `${contenido} ${ultima}`,
        };
    }

    if (estado.programada_para === null) {
        return {
            color: "bg-alerta",
            texto: "Cambios sin publicar",
            detalle: `${contenido} ${ultima} Hay cambios sin publicar y ninguna publicación programada.`,
        };
    }

    const vencida = Date.parse(estado.programada_para) < Date.now();

    // La hora prevista pasó y sigue sin publicarse: lo más probable es que no haya un
    // trabajador de cola corriendo. Ese fallo es mudo —los trabajos se apilan sin más—, y
    // este renglón es el único sitio donde se puede ver.
    return vencida
        ? {
              color: "bg-alerta",
              texto: `Sin publicar · debía salir ${aLaHora(estado.programada_para)}`,
              detalle: `${contenido} ${ultima} La publicación debía dispararse a las ${aLaHora(estado.programada_para)} y no ha ocurrido: puede que no haya un trabajador de cola corriendo en el servidor.`,
          }
        : {
              color: "bg-tinta-suave",
              texto: `Sin publicar · sale ${aLaHora(estado.programada_para)}`,
              detalle: `${contenido} ${ultima} Se publica sola unos minutos después del último cambio.`,
          };
}

export function EstadoDePublicacion() {
    const [estado, setEstado] = useState<EstadoPublicacion | null>(null);
    const { pathname } = useLocation();

    const consultar = useCallback(async (): Promise<void> => {
        try {
            setEstado(await estadoDePublicacion());
        } catch {
            // Silencio a propósito: ver el comentario de arriba.
        }
    }, []);

    useEffect(() => {
        void consultar();
    }, [consultar, pathname]);

    useEffect(() => {
        const reloj = setInterval(() => void consultar(), UN_MINUTO);

        return () => clearInterval(reloj);
    }, [consultar]);

    if (estado === null) {
        return null;
    }

    const aspecto = describir(estado);

    return (
        <details className="group border-b border-borde bg-papel-hondo">
            <summary className="mx-auto flex max-w-6xl cursor-pointer list-none items-center gap-2 px-4 py-2 text-xs sm:px-6 [&::-webkit-details-marker]:hidden">
                <span
                    className={`inline-block size-2 shrink-0 rounded-full ${aspecto.color}`}
                    aria-hidden="true"
                />

                <span className="font-medium text-tinta">{aspecto.texto}</span>

                <span className="ml-auto shrink-0 text-tinta-suave underline-offset-2 group-open:hidden">
                    Ver detalle
                </span>
                <span className="ml-auto hidden shrink-0 text-tinta-suave underline-offset-2 group-open:inline">
                    Ocultar
                </span>
            </summary>

            <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-2 px-4 pb-3 sm:px-6">
                <p className="max-w-prose text-xs text-tinta-suave">
                    {aspecto.detalle}
                </p>

                <button
                    type="button"
                    className="boton-secundario px-2 py-1 text-xs"
                    onClick={() => void consultar()}
                >
                    Actualizar
                </button>
            </div>
        </details>
    );
}
