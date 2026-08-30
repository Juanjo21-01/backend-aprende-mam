/**
 * Las llamadas del panel, una función por operación.
 *
 * Están aquí y no dentro de los componentes para que la forma de la API viva en un solo
 * sitio: si mañana cambia una ruta o una Resource, se cambia acá y el resto compila o falla,
 * en vez de fallar en tiempo de ejecución en una pantalla que nadie abrió.
 *
 * Las Resources de Laravel envuelven en `data`; se desenvuelve aquí para que las pantallas
 * reciban el objeto y no el sobre.
 */

import { api } from './cliente';
import type {
    Categoria,
    CategoriaGramatical,
    Coleccion,
    DatosCategoria,
    DatosEntrada,
    DatosFuente,
    DatosUsuario,
    Entrada,
    FiltrosEntradas,
    Fuente,
    Pagina,
    Sesion,
    Usuario,
} from '../tipos';

/** Quién entró y con qué rol. Lo primero que pide el panel al arrancar. */
export const sesionActual = (): Promise<Sesion> => api.obtener<Sesion>('/yo');

export const listarEntradas = (filtros: FiltrosEntradas): Promise<Pagina<Entrada>> =>
    api.obtener<Pagina<Entrada>>('/entradas', { ...filtros });

export const obtenerEntrada = async (id: number): Promise<Entrada> =>
    (await api.obtener<{ data: Entrada }>(`/entradas/${id}`)).data;

export const crearEntrada = async (datos: DatosEntrada): Promise<Entrada> =>
    (await api.crear<{ data: Entrada }>('/entradas', datos)).data;

export const actualizarEntrada = async (id: number, datos: DatosEntrada): Promise<Entrada> =>
    (await api.reemplazar<{ data: Entrada }>(`/entradas/${id}`, datos)).data;

/** Solo administradores; el editor recibe un 403 con el motivo escrito por la política. */
export const borrarEntrada = (id: number): Promise<void> => api.borrar(`/entradas/${id}`);

/**
 * La firma del validador lingüístico, y el interruptor de lo que se publica: la exportación
 * solo lleva entradas revisadas. Va por su propia ruta, no como un campo más del formulario.
 */
export const marcarRevision = async (id: number, revisado: boolean): Promise<Entrada> =>
    (await api.modificar<{ data: Entrada }>(`/entradas/${id}/revision`, { revisado })).data;

export const listarCategorias = async (): Promise<Categoria[]> =>
    (await api.obtener<Coleccion<Categoria>>('/categorias')).data;

export const listarFuentes = async (): Promise<Fuente[]> =>
    (await api.obtener<Coleccion<Fuente>>('/fuentes')).data;

/** Catálogo normativo de ALMG: alimenta el selector de clase de palabra. */
export const listarClasesDePalabra = async (): Promise<CategoriaGramatical[]> =>
    (await api.obtener<Coleccion<CategoriaGramatical>>('/categorias-gramaticales')).data;

export const obtenerCategoria = async (id: number): Promise<Categoria> =>
    (await api.obtener<{ data: Categoria }>(`/categorias/${id}`)).data;

export const crearCategoria = async (datos: DatosCategoria): Promise<Categoria> =>
    (await api.crear<{ data: Categoria }>('/categorias', datos)).data;

export const actualizarCategoria = async (id: number, datos: DatosCategoria): Promise<Categoria> =>
    (await api.reemplazar<{ data: Categoria }>(`/categorias/${id}`, datos)).data;

/**
 * Borrar un tema no toca las entradas: se pierde la clasificación, no la palabra. Los temas
 * hijos quedan sueltos, con el padre en nulo, y tampoco se borran.
 */
export const borrarCategoria = (id: number): Promise<void> => api.borrar(`/categorias/${id}`);

export const obtenerFuente = async (id: number): Promise<Fuente> =>
    (await api.obtener<{ data: Fuente }>(`/fuentes/${id}`)).data;

export const crearFuente = async (datos: DatosFuente): Promise<Fuente> =>
    (await api.crear<{ data: Fuente }>('/fuentes', datos)).data;

export const actualizarFuente = async (id: number, datos: DatosFuente): Promise<Fuente> =>
    (await api.reemplazar<{ data: Fuente }>(`/fuentes/${id}`, datos)).data;

export const borrarFuente = (id: number): Promise<void> => api.borrar(`/fuentes/${id}`);

/** Todo lo de cuentas es solo para administradores; lo impone `UserPolicy`. */
export const listarUsuarios = async (): Promise<Usuario[]> =>
    (await api.obtener<Coleccion<Usuario>>('/usuarios')).data;

export const obtenerUsuario = async (id: number): Promise<Usuario> =>
    (await api.obtener<{ data: Usuario }>(`/usuarios/${id}`)).data;

export const crearUsuario = async (datos: DatosUsuario): Promise<Usuario> =>
    (await api.crear<{ data: Usuario }>('/usuarios', datos)).data;

export const actualizarUsuario = async (id: number, datos: DatosUsuario): Promise<Usuario> =>
    (await api.reemplazar<{ data: Usuario }>(`/usuarios/${id}`, datos)).data;

/**
 * El camino de recuperación de este sistema: no pide la contraseña anterior —quien la
 * olvidó no la sabe— y cierra las sesiones que esa persona tuviera abiertas.
 */
export const resetearContrasena = async (
    id: number,
    password: string,
    confirmacion: string,
): Promise<Usuario> =>
    (
        await api.modificar<{ data: Usuario }>(`/usuarios/${id}/contrasena`, {
            password,
            password_confirmation: confirmacion,
        })
    ).data;

/** La baja de una cuenta es esta bandera, no un borrado: no hay `destroy` en la API. */
export const cambiarEstadoUsuario = async (id: number, activo: boolean): Promise<Usuario> =>
    (await api.modificar<{ data: Usuario }>(`/usuarios/${id}/estado`, { activo })).data;

/**
 * La contraseña propia, la de cualquiera de los dos roles. Sí pide la actual: un panel
 * abierto y desatendido no puede servir para dejar fuera a su dueño.
 */
export const cambiarContrasenaPropia = (
    actual: string,
    nueva: string,
    confirmacion: string,
): Promise<{ mensaje: string }> =>
    api.modificar<{ mensaje: string }>('/yo/contrasena', {
        current_password: actual,
        password: nueva,
        password_confirmation: confirmacion,
    });
