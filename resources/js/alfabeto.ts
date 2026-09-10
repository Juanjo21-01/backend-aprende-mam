/**
 * El alfabeto Mam, tal como lo sirve el cascarón.
 *
 * Llega incrustado en `admin/panel.blade.php` y no por una petición: son 32 pares que no
 * cambian entre peticiones —el catálogo se re-siembra, no se edita desde el panel— y pedirlos
 * cada vez que se abre un formulario sería una petición para nada. `Alphabet` ya es singleton
 * en el servidor, así que incrustarlo no cuesta ni una consulta de más.
 *
 * Se lee una vez, al cargar el módulo. Si el cascarón no lo trajo —una vista vieja en caché,
 * un test que monta el componente suelto— queda vacío y el teclado no se dibuja. Es lo
 * correcto: sin catálogo no hay nada que ofrecer, y el campo se sigue pudiendo escribir a
 * mano.
 */

export interface Grafema {
    grafema: string;
    posicion: number;
}

function leerDelCascaron(): Grafema[] {
    const etiqueta = document.getElementById('alfabeto-mam');

    if (etiqueta === null || etiqueta.textContent === null) {
        return [];
    }

    try {
        const crudo: unknown = JSON.parse(etiqueta.textContent);

        if (crudo === null || typeof crudo !== 'object') {
            return [];
        }

        return Object.entries(crudo as Record<string, number>)
            .map(([grafema, posicion]) => ({ grafema, posicion }))
            .sort((uno, otro) => uno.posicion - otro.posicion);
    } catch {
        return [];
    }
}

export const ALFABETO: Grafema[] = leerDelCascaron();

/**
 * Las letras que un teclado de castellano no puede escribir.
 *
 * La prueba es mecánica y no ortográfica: **¿el grafema lleva algún carácter que no sea
 * `a`–`z`?** Con el catálogo de hoy salen exactamente diez —las ocho glotalizadas, la `ẍ` y el
 * saltillo suelto—, y las veintidós restantes se quedan fuera porque ya están en el teclado.
 *
 * Se hace así a propósito. Decidir «cuáles son glotalizadas» sería conocimiento ortográfico, y
 * ese vive entero en `app/Support/Mam/` y no se reparte por el frontend. Preguntar si una
 * cadena sale del alfabeto latino básico no es saber Mam: es saber qué teclas hay. Y si algún
 * día COLIMAM ratifica una letra nueva con diacrítico, aparece sola.
 */
export const GRAFEMAS_QUE_NO_ESTAN_EN_EL_TECLADO: Grafema[] = ALFABETO.filter(({ grafema }) =>
    /[^a-z]/.test(grafema),
);
