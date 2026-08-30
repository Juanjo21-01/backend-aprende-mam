/**
 * Cliente HTTP del panel.
 *
 * Toda petición a `/api/v1/admin` pasa por aquí. La autenticación es la cookie de sesión
 * del mismo origen (`auth:sanctum` sobre `statefulApi`), no un token: por eso no hay nada
 * que guardar en `localStorage` y por eso el panel tiene que vivir donde vive.
 *
 * Tres cosas que este archivo resuelve y que fallan de una forma que no señala la causa:
 *
 * 1. **El token CSRF cambia al iniciar sesión.** Laravel regenera la sesión contra la
 *    fijación de sesión y con ella el token. Se lee la cookie `XSRF-TOKEN` en *cada*
 *    petición y no una sola vez al arrancar; guardarlo produce un 419 en la primera
 *    escritura y ningún indicio de por qué.
 * 2. **Un 401 no es un error de la pantalla, es una sesión que se acabó.** Se manda al
 *    login en vez de pintar un aviso que el usuario no puede resolver.
 * 3. **`Accept: application/json`** no es decorativo: sin él Laravel contesta la
 *    validación con un redirect en lugar de un 422 con la bolsa de errores.
 *
 * La cabecera `Referer` que exige `EnsureFrontendRequestsAreStateful` la manda el
 * navegador solo. Lo sufre Postman, no esto.
 */

const BASE = '/api/v1/admin';

/** A dónde se manda al usuario cuando la sesión ya no vale. */
const RUTA_LOGIN = '/admin/login';

export type BolsaDeErrores = Record<string, string[]>;

/**
 * Un fallo con el que la pantalla puede hacer algo: sabe el código y, si fue una
 * validación, qué campo se quejó.
 */
export class ErrorApi extends Error {
    readonly estado: number;

    readonly errores: BolsaDeErrores;

    constructor(estado: number, mensaje: string, errores: BolsaDeErrores = {}) {
        super(mensaje);
        this.name = 'ErrorApi';
        this.estado = estado;
        this.errores = errores;
    }

    /** El primer mensaje de un campo, para pintarlo debajo de su casilla. */
    campo(nombre: string): string | undefined {
        return this.errores[nombre]?.[0];
    }

    /** Un 403 trae el motivo que escribió la política; conviene mostrarlo tal cual. */
    get esPermiso(): boolean {
        return this.estado === 403;
    }
}

function tokenCsrf(): string {
    const cookie = document.cookie
        .split('; ')
        .find((trozo) => trozo.startsWith('XSRF-TOKEN='));

    if (cookie !== undefined) {
        return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
    }

    // Respaldo: el cascarón Blade siempre trae el meta, aunque la cookie falte.
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/** Descarta lo vacío: un filtro sin valor no debe viajar en la URL. */
function consulta(params: Record<string, unknown> | undefined): string {
    if (params === undefined) {
        return '';
    }

    const busqueda = new URLSearchParams();

    for (const [clave, valor] of Object.entries(params)) {
        if (valor === undefined || valor === null || valor === '') {
            continue;
        }

        busqueda.set(clave, typeof valor === 'boolean' ? (valor ? '1' : '0') : String(valor));
    }

    const cadena = busqueda.toString();

    return cadena === '' ? '' : `?${cadena}`;
}

async function fallo(respuesta: Response): Promise<never> {
    let mensaje = `Error ${respuesta.status}`;
    let errores: BolsaDeErrores = {};

    try {
        const cuerpo = await respuesta.json();

        if (typeof cuerpo?.message === 'string' && cuerpo.message !== '') {
            mensaje = cuerpo.message;
        }

        if (cuerpo?.errors !== undefined && cuerpo.errors !== null) {
            errores = cuerpo.errors as BolsaDeErrores;
        }
    } catch {
        // Una respuesta sin JSON —el HTML de una excepción, por ejemplo— deja el mensaje
        // genérico. Mejor eso que romper el panel encima del fallo original.
    }

    throw new ErrorApi(respuesta.status, mensaje, errores);
}

async function peticion<T>(
    metodo: string,
    ruta: string,
    cuerpo?: unknown,
    params?: Record<string, unknown>,
): Promise<T> {
    const respuesta = await fetch(`${BASE}${ruta}${consulta(params)}`, {
        method: metodo,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': tokenCsrf(),
            ...(cuerpo === undefined ? {} : { 'Content-Type': 'application/json' }),
        },
        body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
    });

    if (respuesta.status === 401) {
        // La sesión caducó o la cuenta se desactivó mientras el panel estaba abierto.
        window.location.assign(RUTA_LOGIN);

        throw new ErrorApi(401, 'La sesión terminó.');
    }

    if (respuesta.status === 419) {
        // Token vencido. Recargar trae uno nuevo, y si la sesión ya no existe, el
        // middleware `auth` manda al login, que es lo que corresponde.
        window.location.reload();

        throw new ErrorApi(419, 'La sesión terminó.');
    }

    if (!respuesta.ok) {
        return fallo(respuesta);
    }

    // 204 de un borrado: no hay cuerpo que leer.
    if (respuesta.status === 204) {
        return undefined as T;
    }

    return (await respuesta.json()) as T;
}

export const api = {
    obtener: <T>(ruta: string, params?: Record<string, unknown>): Promise<T> =>
        peticion<T>('GET', ruta, undefined, params),

    crear: <T>(ruta: string, cuerpo: unknown): Promise<T> => peticion<T>('POST', ruta, cuerpo),

    reemplazar: <T>(ruta: string, cuerpo: unknown): Promise<T> => peticion<T>('PUT', ruta, cuerpo),

    modificar: <T>(ruta: string, cuerpo: unknown): Promise<T> => peticion<T>('PATCH', ruta, cuerpo),

    borrar: (ruta: string): Promise<void> => peticion<void>('DELETE', ruta),
};
