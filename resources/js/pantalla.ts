/**
 * Si la ventana da de sí para la vista de tabla.
 *
 * El listado de entradas se dibuja como tabla en escritorio y como fichas en el teléfono, y
 * son dos marcados distintos, no el mismo con otro CSS: una fila de siete columnas no se
 * convierte en una ficha legible cambiando unas clases. Se decide aquí, en un solo sitio, y
 * se dibuja **uno** de los dos.
 *
 * Pintar los dos y esconder uno con `hidden` habría sido más corto y es lo que suele hacerse.
 * Se descartó porque obliga a tocar cada fila dos veces cada vez que cambie algo, y en un
 * proyecto que mantiene una sola persona eso acaba en dos versiones de la misma fila que ya
 * no dicen lo mismo.
 *
 * El valor inicial se lee de forma síncrona: con `useState(false)` y un efecto, un escritorio
 * enseñaría las fichas durante el primer fotograma y luego saltaría a la tabla.
 *
 * El umbral es el `md` de Tailwind, 768 px, y sale de medir la tabla: casilla 32, Mam 110,
 * castellano 160, clase 60, temas 140, revisión 100 y acciones 90, más el relleno, caben
 * justo. Con el `lg` que tenía antes, una tableta de 800 px enseñaba fichas y desperdiciaba
 * media pantalla.
 *
 * Va en píxeles y no en `rem` a propósito: `matchMedia` resuelve `rem` contra el tamaño de
 * fuente de la raíz, y si algún día el panel lo cambia, el corte de JavaScript y el de CSS
 * dejarían de coincidir sin que nada avise.
 */

import { useEffect, useState } from 'react';

const CABE_LA_TABLA = '(min-width: 768px)';

export function useCabeLaTabla(): boolean {
    const [cabeLaTabla, setCabeLaTabla] = useState<boolean>(
        () => window.matchMedia(CABE_LA_TABLA).matches,
    );

    useEffect(() => {
        const consulta = window.matchMedia(CABE_LA_TABLA);

        function alCambiar(evento: MediaQueryListEvent): void {
            setCabeLaTabla(evento.matches);
        }

        // Se vuelve a leer al montar por si la ventana cambió entre el primer render y este
        // efecto, que es justo lo que pasa al girar el teléfono nada más abrir.
        setCabeLaTabla(consulta.matches);
        consulta.addEventListener('change', alCambiar);

        return () => consulta.removeEventListener('change', alCambiar);
    }, []);

    return cabeLaTabla;
}
