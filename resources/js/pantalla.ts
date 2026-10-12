/**
 * Si la ventana da de sí para la vista de tabla.
 *
 * Los listados se dibujan como tabla en escritorio y como fichas en el teléfono, y son dos
 * marcados distintos, no el mismo con otro CSS: una fila de siete columnas no se convierte en
 * una ficha legible cambiando unas clases. Se decide aquí, en un solo sitio, y se dibuja
 * **uno** de los dos.
 *
 * Pintar los dos y esconder uno con `hidden` habría sido más corto y es lo que suele hacerse.
 * Se descartó porque obliga a tocar cada fila dos veces cada vez que cambie algo, y en un
 * proyecto que mantiene una sola persona eso acaba en dos versiones de la misma fila que ya
 * no dicen lo mismo.
 *
 * El valor inicial se lee de forma síncrona: con `useState(false)` y un efecto, un escritorio
 * enseñaría las fichas durante el primer fotograma y luego saltaría a la tabla.
 *
 * Va en píxeles y no en `rem` a propósito: `matchMedia` resuelve `rem` contra el tamaño de
 * fuente de la raíz, y si algún día el panel lo cambia, el corte de JavaScript y el de CSS
 * dejarían de coincidir sin que nada avise.
 */

import { useEffect, useState } from 'react';

/**
 * UN UMBRAL POR TABLA, Y CADA UNO MEDIDO.
 *
 * Antes las cuatro compartían 768 px. No era cierto: a 768 ninguna desbordaba pero todas se
 * aplastaban —filas de 81 px en Entradas y 115 en Cuentas, «Editar» y «Borrar» apilados, el
 * slug partido en dos renglones—, y una ficha se lee mejor que eso.
 *
 * El número es el ancho al que la tabla deja de romper renglones, medido en el navegador con
 * contenido de verdad (título de la ALMG, slug largo, correo real), más el relleno del `main`
 * y la barra de desplazamiento. Si se añade o se ensancha una columna, hay que volver a
 * medirlo: no es cuestión de gusto.
 *
 * Fuentes pide 1275 y no cabe ni en `max-w-6xl`; se queda en 1024 y su título envuelve a
 * propósito, que para eso es un título.
 */
export const ANCHO_TABLA = {
    entradas: 840,
    temas: 980,
    cuentas: 1000,
    fuentes: 1024,
} as const;

export function useCabeLaTabla(minimo: number): boolean {
    const consulta = `(min-width: ${minimo}px)`;

    const [cabeLaTabla, setCabeLaTabla] = useState<boolean>(
        () => window.matchMedia(consulta).matches,
    );

    useEffect(() => {
        const medio = window.matchMedia(`(min-width: ${minimo}px)`);

        function alCambiar(evento: MediaQueryListEvent): void {
            setCabeLaTabla(evento.matches);
        }

        // Se vuelve a leer al montar por si la ventana cambió entre el primer render y este
        // efecto, que es justo lo que pasa al girar el teléfono nada más abrir.
        setCabeLaTabla(medio.matches);
        medio.addEventListener('change', alCambiar);

        return () => medio.removeEventListener('change', alCambiar);
    }, [minimo]);

    return cabeLaTabla;
}
