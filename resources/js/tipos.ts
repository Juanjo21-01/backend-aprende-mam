/**
 * El contrato de `/api/v1/admin`, transcrito de las Resources de Laravel.
 *
 * Los nombres de campo van en castellano porque así los emite el backend; el dominio del
 * proyecto está en castellano y las Resources no traducen. Ojo con dos formas que se
 * parecen y no son lo mismo: esta es la salida del **panel**, no la de la exportación que
 * consume el sitio de Astro. Ahí `categorias` son slugs y `clase` es una cadena.
 */

export type Rol = 'administrador' | 'editor';

/** Lo que devuelve `GET /yo`. Decide qué botones se ofrecen, no qué se permite. */
export interface Sesion {
    id: number;
    nombre: string;
    correo: string;
    rol: Rol;
    rol_nombre: string;
    es_administrador: boolean;
}

/** Catálogo normativo de ALMG. Se lee, no se edita. */
export interface CategoriaGramatical {
    id: number;
    abreviatura: string;
    nombre: string;
    descripcion: string | null;
}

export interface Fuente {
    id: number;
    titulo: string;
    institucion: string | null;
    anio: number | null;
    licencia: string | null;
    url: string | null;
    total_entradas?: number;
}

export interface Categoria {
    id: number;
    nombre_es: string;
    nombre_mam: string | null;
    slug: string;
    icono: string | null;
    orden: number;
    padre_id: number | null;
    hijas?: Categoria[];
    total_entradas?: number;
}

/**
 * Autoría en forma reducida. Puede ser `null` con la relación cargada: una entrada que
 * entró por seeder o por el importador no la cargó ninguna persona, y una sin revisar no
 * tiene revisor.
 */
export interface Autor {
    id: number;
    nombre: string;
}

export interface Entrada {
    id: number;
    mam: string;
    espanol: string;
    definicion: string | null;

    /**
     * Derivadas: las calcula el mutator del modelo a partir de `mam` y no se mandan nunca.
     * El formulario las enseña de solo lectura porque el Manual de Normas le pide al editor
     * que reporte un orden que le parezca equivocado, y sin verlas no tiene con qué.
     */
    busqueda: string;
    orden_alfabetico: string;

    municipio: string | null;
    pagina_fuente: string | null;
    revisado: boolean;
    revisado_en: string | null;
    creado_por?: Autor | null;
    revisado_por?: Autor | null;
    categoria_gramatical?: CategoriaGramatical | null;
    fuente?: Fuente | null;
    categorias?: Categoria[];
    creado_en: string;
    actualizado_en: string;
}

/**
 * Lo que acepta `EntradaRequest`. No lleva `revisado`, `busqueda` ni `orden_alfabetico`:
 * el backend responde `prohibited` —no los ignora— si alguno viaja en el formulario.
 */
export interface DatosEntrada {
    mam: string;
    espanol: string;
    definicion: string | null;
    categoria_gramatical_id: number | null;
    municipio: string | null;
    fuente_id: number | null;
    pagina_fuente: string | null;
    categorias: number[];
}

/** Filtros de `GET /entradas`. Los que van vacíos no se mandan. */
export interface FiltrosEntradas {
    buscar?: string;
    revisado?: boolean;
    categoria?: number;
    por_pagina?: number;
    page?: number;
}

/** Colección paginada de Laravel. */
export interface Pagina<T> {
    data: T[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}

/** Colección sin paginar: categorías, fuentes y clases de palabra vienen enteras. */
export interface Coleccion<T> {
    data: T[];
}

/**
 * Lo que acepta `CategoriaRequest`.
 *
 * `nombre_mam` es texto en Mam y pasa por el normalizador del modelo, igual que el lema de
 * una entrada. `slug` no: va en la URL del sitio estático y solo admite minúsculas, números
 * y guiones.
 */
export interface DatosCategoria {
    nombre_es: string;
    nombre_mam: string | null;
    slug: string;
    icono: string | null;
    orden: number | null;
    padre_id: number | null;
}

/** Lo que acepta `FuenteRequest`. Nada de esto se normaliza: un título es una cita. */
export interface DatosFuente {
    titulo: string;
    institucion: string | null;
    anio: number | null;
    licencia: string | null;
    url: string | null;
}

/**
 * Una cuenta del panel, tal como la devuelve `UserResource`.
 *
 * Ojo con la asimetría: la Resource responde en castellano (`nombre`, `correo`) y el
 * FormRequest espera en inglés (`name`, `email`), porque esas son las columnas de `users`,
 * que vienen del esqueleto de Laravel. Confundirlas da un 422 sin pistas.
 *
 * Los dos últimos campos existen para que el panel no ofrezca acciones que van a terminar
 * en 422: no tiene sentido enseñar «desactivar» sobre la única cuenta que puede administrar
 * el sistema, ni sobre la de quien está mirando la pantalla.
 */
export interface Usuario {
    id: number;
    nombre: string;
    correo: string;
    rol: Rol;
    rol_nombre: string;
    activo: boolean;
    es_el_ultimo_administrador: boolean;
    es_uno_mismo: boolean;
    creado_en: string;
}

/** Lo que acepta `UserRequest`. La contraseña solo viaja al crear; al editar es `prohibited`. */
export interface DatosUsuario {
    name: string;
    email: string;
    rol: Rol;
    password?: string;
    password_confirmation?: string;
}
