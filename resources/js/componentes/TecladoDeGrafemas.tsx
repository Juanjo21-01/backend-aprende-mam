/**
 * Las letras del Mam que no están en el teclado, para insertarlas donde esté el cursor.
 *
 * Es la única pieza del panel que no podría aparecer en ningún otro panel: sale del catálogo
 * `grafemas`, que es un documento normativo, no una tabla de configuración.
 *
 * Y no es un adorno. Transcribiendo del COLIMAM, escribir `tx’otx’` en un teclado de Windows
 * obliga a cazar el apóstrofo tipográfico U+2019 —el recto que sale de la tecla es otro
 * carácter, y aunque el normalizador lo corrija al guardar, el editor escribe a ciegas—, y la
 * `ẍ` pide un código alt que nadie se sabe. Son las dos correcciones que el Manual de Normas
 * promete que el sistema hace solo; esto es no tener que necesitarlas.
 *
 * TRES DETALLES QUE NO SE VEN:
 *
 * 1. **`onMouseDown` cancelado.** Sin eso, pulsar una tecla saca el foco del campo antes del
 *    clic, y la posición del cursor —que es dónde hay que insertar— se pierde.
 * 2. **El cursor se repone después de repintar.** El campo lo controla React: escribir el
 *    valor nuevo lo vuelve a dibujar y el cursor se va al final. Se guarda dónde tiene que
 *    quedar y se repone en un efecto, ya con el valor nuevo puesto.
 * 3. **Si hay texto seleccionado, se sustituye.** Es lo que hace el teclado de verdad, y es lo
 *    que se necesita para arreglar una letra mal escrita sin borrarla antes.
 */

import { useEffect, useState, type RefObject } from 'react';

import { GRAFEMAS_QUE_NO_ESTAN_EN_EL_TECLADO } from '../alfabeto';

export function TecladoDeGrafemas({
    campo,
    valor,
    alCambiar,
}: {
    campo: RefObject<HTMLInputElement | null>;
    valor: string;
    alCambiar: (valor: string) => void;
}) {
    const [cursor, setCursor] = useState<number | null>(null);

    useEffect(() => {
        if (cursor === null) {
            return;
        }

        const elemento = campo.current;

        if (elemento !== null) {
            elemento.focus();
            elemento.setSelectionRange(cursor, cursor);
        }

        setCursor(null);
    }, [cursor, campo, valor]);

    if (GRAFEMAS_QUE_NO_ESTAN_EN_EL_TECLADO.length === 0) {
        return null;
    }

    function insertar(grafema: string): void {
        const elemento = campo.current;

        if (elemento === null) {
            return;
        }

        const inicio = elemento.selectionStart ?? valor.length;
        const fin = elemento.selectionEnd ?? inicio;

        alCambiar(valor.slice(0, inicio) + grafema + valor.slice(fin));
        setCursor(inicio + grafema.length);
    }

    return (
        <>
            <div
                role="group"
                aria-label="Letras del Mam que no están en el teclado"
                className="mt-2 flex flex-wrap gap-1"
            >
                {GRAFEMAS_QUE_NO_ESTAN_EN_EL_TECLADO.map(({ grafema, posicion }) => (
                    <button
                        key={grafema}
                        type="button"
                        className="tecla-grafema"
                        // La posición es la misma que aparece en la clave de orden, abajo, en
                        // pares. Quien quiera comprobar por qué una palabra ordena donde
                        // ordena, tiene aquí de dónde sacar los números.
                        title={`Letra ${posicion} de 32`}
                        onMouseDown={(evento) => evento.preventDefault()}
                        onClick={() => insertar(grafema)}
                    >
                        <span className="mam" lang="mam">{grafema}</span>
                    </button>
                ))}
            </div>

            <p className="ayuda">
                Las letras del Mam que no están en el teclado. Se insertan donde esté el
                cursor.
            </p>
        </>
    );
}
