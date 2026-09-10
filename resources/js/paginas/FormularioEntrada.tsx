/**
 * Alta y edición de una entrada.
 *
 * Tres decisiones de esta pantalla, todas por el mismo motivo —que el trabajo real es
 * transcribir cientos de palabras de un libro, no editar una de vez en cuando:
 *
 * 1. **«Guardar y cargar otra»** conserva la fuente, la página, los temas y la clase de
 *    palabra, y solo vacía la palabra, la traducción y la definición. Transcribiendo una
 *    página del COLIMAM eso es lo único que cambia entre una entrada y la siguiente.
 * 2. **Se enseña cómo quedó guardada la palabra** cuando el normalizador la cambió. El
 *    Manual de Normas le promete al editor que el panel corrige solo el apóstrofo recto y
 *    la `õ` de los PDF, y que «es el sistema trabajando bien, no un error». Si no se ve, la
 *    promesa no se cumple: la corrección es invisible en pantalla.
 * 3. **La clave de búsqueda y la de orden se muestran, en gris y sin poder tocarlas.** Son
 *    columnas derivadas; el Manual pide que el editor reporte un orden que le parezca
 *    equivocado, y sin verlo no tiene con qué.
 */

import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';

import { ErrorApi } from '../api/cliente';
import { actualizarEntrada, crearEntrada, marcarRevision, obtenerEntrada } from '../api/recursos';
import { Aviso, Cargando, Exito } from '../componentes/Estados';
import { TecladoDeGrafemas } from '../componentes/TecladoDeGrafemas';
import { usePanel } from '../panel';
import type { DatosEntrada, Entrada } from '../tipos';

/** El formulario trabaja con cadenas; la conversión a la forma de la API va al enviar. */
interface Formulario {
    mam: string;
    espanol: string;
    definicion: string;
    categoria_gramatical_id: string;
    municipio: string;
    fuente_id: string;
    pagina_fuente: string;
    categorias: number[];
}

const VACIO: Formulario = {
    mam: '',
    espanol: '',
    definicion: '',
    categoria_gramatical_id: '',
    municipio: '',
    fuente_id: '',
    pagina_fuente: '',
    categorias: [],
};

/**
 * La clave de orden, separada en pares.
 *
 * Es una cadena de posiciones del alfabeto, dos dígitos por letra: `24310114` son **cuatro**
 * letras, no ocho, porque los dígrafos y las glotalizadas ocupan una sola posición. De
 * corrido no hay forma de comprobarla; separada, el editor puede contar los grafemas de su
 * palabra y ver si el número cuadra. El Manual de Normas le pide que reporte un orden que le
 * parezca equivocado, y sin poder leerlo no tiene con qué.
 */
function enPares(clave: string): string {
    return clave.replace(/\d{2}/g, '$& ').trimEnd();
}

function desdeEntrada(entrada: Entrada): Formulario {
    return {
        mam: entrada.mam,
        espanol: entrada.espanol,
        definicion: entrada.definicion ?? '',
        categoria_gramatical_id: entrada.categoria_gramatical?.id?.toString() ?? '',
        municipio: entrada.municipio ?? '',
        fuente_id: entrada.fuente?.id?.toString() ?? '',
        pagina_fuente: entrada.pagina_fuente ?? '',
        categorias: entrada.categorias?.map((tema) => tema.id) ?? [],
    };
}

function aDatos(formulario: Formulario): DatosEntrada {
    const texto = (valor: string): string | null => (valor.trim() === '' ? null : valor);
    const numero = (valor: string): number | null => (valor === '' ? null : Number(valor));

    return {
        mam: formulario.mam,
        espanol: formulario.espanol,
        definicion: texto(formulario.definicion),
        categoria_gramatical_id: numero(formulario.categoria_gramatical_id),
        municipio: texto(formulario.municipio),
        fuente_id: numero(formulario.fuente_id),
        pagina_fuente: texto(formulario.pagina_fuente),
        categorias: formulario.categorias,
    };
}

export function FormularioEntrada() {
    const { id } = useParams();
    const navegar = useNavigate();
    const { sesion, categorias, fuentes, clases } = usePanel();

    const esAlta = id === undefined;

    const [formulario, setFormulario] = useState<Formulario>(VACIO);
    const [entrada, setEntrada] = useState<Entrada | null>(null);
    const [cargando, setCargando] = useState(!esAlta);
    const [guardando, setGuardando] = useState(false);
    const [fallo, setFallo] = useState<ErrorApi | Error | null>(null);
    const [guardada, setGuardada] = useState<Entrada | null>(null);
    const [tecleado, setTecleado] = useState('');

    const campoMam = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (esAlta) {
            setFormulario(VACIO);
            setEntrada(null);
            setCargando(false);

            return;
        }

        let cancelado = false;
        setCargando(true);

        obtenerEntrada(Number(id))
            .then((cargada) => {
                if (cancelado) {
                    return;
                }

                setEntrada(cargada);
                setFormulario(desdeEntrada(cargada));
                setFallo(null);
            })
            .catch((error: unknown) => {
                if (!cancelado) {
                    setFallo(error instanceof Error ? error : new Error('No se pudo cargar la entrada.'));
                }
            })
            .finally(() => {
                if (!cancelado) {
                    setCargando(false);
                }
            });

        return () => {
            cancelado = true;
        };
    }, [id, esAlta]);

    function cambiar<C extends keyof Formulario>(campo: C, valor: Formulario[C]): void {
        setFormulario((previo) => ({ ...previo, [campo]: valor }));
    }

    function alternarTema(temaId: number): void {
        setFormulario((previo) => ({
            ...previo,
            categorias: previo.categorias.includes(temaId)
                ? previo.categorias.filter((otro) => otro !== temaId)
                : [...previo.categorias, temaId],
        }));
    }

    async function guardar(seguirCargando: boolean): Promise<void> {
        setGuardando(true);
        setFallo(null);
        setGuardada(null);
        setTecleado(formulario.mam);

        try {
            const datos = aDatos(formulario);

            const grabada = esAlta
                ? await crearEntrada(datos)
                : await actualizarEntrada(Number(id), datos);

            setGuardada(grabada);

            if (esAlta && seguirCargando) {
                // Se conserva el contexto de la transcripción: misma fuente, misma página,
                // mismos temas, misma clase. Solo se vacía lo que cambia palabra a palabra.
                setFormulario((previo) => ({ ...previo, mam: '', espanol: '', definicion: '' }));
                campoMam.current?.focus();

                return;
            }

            if (esAlta) {
                navegar('/entradas');

                return;
            }

            // En una edición se sigue en la pantalla: es donde se ve si el normalizador
            // tocó la palabra y cómo quedaron las claves derivadas.
            setEntrada(grabada);
            setFormulario(desdeEntrada(grabada));
        } catch (error) {
            setFallo(error instanceof Error ? error : new Error('No se pudo guardar.'));
        } finally {
            setGuardando(false);
        }
    }

    async function alternarRevision(): Promise<void> {
        if (entrada === null) {
            return;
        }

        setGuardando(true);
        setFallo(null);

        try {
            const actualizada = await marcarRevision(entrada.id, !entrada.revisado);
            setEntrada(actualizada);
        } catch (error) {
            setFallo(error instanceof Error ? error : new Error('No se pudo cambiar la revisión.'));
        } finally {
            setGuardando(false);
        }
    }

    const errorDe = (campo: string): string | undefined =>
        fallo instanceof ErrorApi ? fallo.campo(campo) : undefined;

    /** Un 403 o un 500 no cuelgan de ningún campo: van arriba, enteros. */
    const errorGeneral =
        fallo === null ? null : fallo instanceof ErrorApi && fallo.estado === 422 ? null : fallo.message;

    const claseElegida = clases.find(
        (clase) => clase.id.toString() === formulario.categoria_gramatical_id,
    );

    const corregida = guardada !== null && tecleado !== '' && guardada.mam !== tecleado;

    if (cargando) {
        return <Cargando texto="Cargando la entrada…" />;
    }

    return (
        <div className="grid max-w-3xl gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold">{esAlta ? 'Nueva entrada' : 'Editar entrada'}</h1>

                <Link to="/entradas" className="enlace text-sm text-tinta-suave">
                    Volver al listado
                </Link>
            </div>

            {errorGeneral !== null && <Aviso>{errorGeneral}</Aviso>}

            {guardada !== null && (
                <Exito
                    detalle={
                        corregida && (
                            <p>
                                Se escribió «<span className="mam" lang="mam">{tecleado}</span>» y el panel lo
                                corrigió al guardar: el apóstrofo queda en su forma canónica, la «õ»
                                que llega corrupta de los PDF vuelve a ser «ẍ» y el texto se
                                normaliza. Es el sistema trabajando bien, no un error.
                            </p>
                        )
                    }
                >
                    Se guardó{" "}
                    <strong className="mam font-semibold" lang="mam">{guardada.mam}</strong>.
                </Exito>
            )}

            <form
                className="tarjeta grid gap-4 p-5"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    void guardar(false);
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="etiqueta" htmlFor="mam">
                            Palabra en Mam
                        </label>
                        {/*
                            El único campo del panel que se escribe en Charis SIL, y a cuerpo
                            mayor que el resto. Es la palabra que se teclea cientos de veces y
                            la que hay que mirar de cerca: en una sans, la `ẍ` la resuelve una
                            fuente sustituta y el apóstrofo recto (') se ve igual que el
                            canónico (’). Aquí se ven distintos, que es de lo que se trata.

                            `spellCheck` apagado: el corrector del navegador no conoce el Mam y
                            subraya en rojo cada palabra.
                        */}
                        <input
                            id="mam"
                            ref={campoMam}
                            className="campo mam text-base"
                            lang="mam"
                            value={formulario.mam}
                            onChange={(evento) => cambiar('mam', evento.target.value)}
                            required
                            autoFocus
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <TecladoDeGrafemas
                            campo={campoMam}
                            valor={formulario.mam}
                            alCambiar={(nuevo) => cambiar('mam', nuevo)}
                        />

                        {errorDe('mam') !== undefined && (
                            <p className="error-campo">{errorDe('mam')}</p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="espanol">
                            Traducción al castellano
                        </label>
                        <input
                            id="espanol"
                            className="campo"
                            value={formulario.espanol}
                            onChange={(evento) => cambiar('espanol', evento.target.value)}
                            required
                            autoComplete="off"
                        />
                        {errorDe('espanol') !== undefined && (
                            <p className="error-campo">{errorDe('espanol')}</p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="etiqueta" htmlFor="definicion">
                        Definición <span className="font-normal text-tinta-suave">(opcional)</span>
                    </label>
                    <textarea
                        id="definicion"
                        className="campo"
                        rows={3}
                        value={formulario.definicion}
                        onChange={(evento) => cambiar('definicion', evento.target.value)}
                    />
                    {errorDe('definicion') !== undefined && (
                        <p className="error-campo">{errorDe('definicion')}</p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="etiqueta" htmlFor="clase">
                            Clase de palabra
                        </label>
                        <select
                            id="clase"
                            className="campo"
                            value={formulario.categoria_gramatical_id}
                            onChange={(evento) => cambiar('categoria_gramatical_id', evento.target.value)}
                        >
                            <option value="">Sin especificar</option>
                            {clases.map((clase) => (
                                <option key={clase.id} value={clase.id}>
                                    {clase.nombre} ({clase.abreviatura})
                                </option>
                            ))}
                        </select>

                        {/* Las cuatro clases mayas no tienen equivalente en castellano: esto es
                            lo que le dice al editor qué está eligiendo. */}
                        {claseElegida?.descripcion != null && (
                            <p className="ayuda">{claseElegida.descripcion}</p>
                        )}
                        {errorDe('categoria_gramatical_id') !== undefined && (
                            <p className="error-campo">{errorDe('categoria_gramatical_id')}</p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="municipio">
                            Municipio <span className="font-normal text-tinta-suave">(opcional)</span>
                        </label>
                        <input
                            id="municipio"
                            className="campo"
                            value={formulario.municipio}
                            onChange={(evento) => cambiar('municipio', evento.target.value)}
                            placeholder="San Marcos"
                            autoComplete="off"
                        />
                        {errorDe('municipio') !== undefined && (
                            <p className="error-campo">{errorDe('municipio')}</p>
                        )}
                    </div>
                </div>

                <fieldset>
                    <legend className="etiqueta">Temas</legend>

                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        {categorias.map((tema) => (
                            <label key={tema.id} className="casilla text-sm">
                                <input
                                    type="checkbox"
                                    checked={formulario.categorias.includes(tema.id)}
                                    onChange={() => alternarTema(tema.id)}
                                />
                                {tema.nombre_es}
                            </label>
                        ))}
                    </div>

                    {errorDe('categorias') !== undefined && (
                        <p className="error-campo">{errorDe('categorias')}</p>
                    )}
                </fieldset>

                <div className="grid gap-4 sm:grid-cols-[1fr_10rem]">
                    <div>
                        <label className="etiqueta" htmlFor="fuente">
                            Fuente bibliográfica
                        </label>
                        <select
                            id="fuente"
                            className="campo"
                            value={formulario.fuente_id}
                            onChange={(evento) => cambiar('fuente_id', evento.target.value)}
                        >
                            <option value="">Sin fuente</option>
                            {fuentes.map((fuente) => (
                                <option key={fuente.id} value={fuente.id}>
                                    {fuente.titulo}
                                </option>
                            ))}
                        </select>
                        {errorDe('fuente_id') !== undefined && (
                            <p className="error-campo">{errorDe('fuente_id')}</p>
                        )}
                    </div>

                    <div>
                        <label className="etiqueta" htmlFor="pagina">
                            Página
                        </label>
                        <input
                            id="pagina"
                            className="campo"
                            value={formulario.pagina_fuente}
                            onChange={(evento) => cambiar('pagina_fuente', evento.target.value)}
                            autoComplete="off"
                        />
                        {errorDe('pagina_fuente') !== undefined && (
                            <p className="error-campo">{errorDe('pagina_fuente')}</p>
                        )}
                    </div>
                </div>

                <div className="pie-formulario">
                    <button type="submit" className="boton" disabled={guardando}>
                        {guardando ? 'Guardando…' : 'Guardar'}
                    </button>

                    {esAlta && (
                        <button
                            type="button"
                            className="boton-secundario"
                            disabled={guardando}
                            onClick={() => void guardar(true)}
                        >
                            Guardar y cargar otra
                        </button>
                    )}

                    <Link to="/entradas" className="enlace text-sm text-tinta-suave">
                        Cancelar
                    </Link>

                    {esAlta && (
                        <p className="w-full text-xs text-tinta-suave">
                            «Guardar y cargar otra» conserva la fuente, la página, los temas y la
                            clase de palabra.
                        </p>
                    )}
                </div>
            </form>

            {entrada !== null && (
                <div className="tarjeta grid gap-3 p-5 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            {/* El mismo rombo que marca la fila en el listado, con el mismo
                                significado. El color no es el canal: la frase de abajo dice
                                si se publica o no. */}
                            <p className="flex items-center gap-2 font-medium">
                                <span
                                    className={
                                        entrada.revisado
                                            ? 'marca-revision'
                                            : 'marca-revision-hueca'
                                    }
                                    aria-hidden="true"
                                />
                                Revisión lingüística
                            </p>
                            <p className="mt-0.5 text-xs text-tinta-suave">
                                {entrada.revisado
                                    ? `Revisada${entrada.revisado_por != null ? ` por ${entrada.revisado_por.nombre}` : ''}. Se publica.`
                                    : 'Sin revisar. No llega al sitio público.'}
                            </p>
                        </div>

                        {sesion.es_administrador ? (
                            <button
                                type="button"
                                className="boton-secundario"
                                disabled={guardando}
                                onClick={() => void alternarRevision()}
                            >
                                {entrada.revisado ? 'Quitar la revisión' : 'Marcar como revisada'}
                            </button>
                        ) : (
                            <p className="text-xs text-tinta-suave">Solo el validador lingüístico la marca.</p>
                        )}
                    </div>

                    <dl className="grid gap-2 border-t border-borde pt-3 text-xs sm:grid-cols-2">
                        <div>
                            <dt className="text-tinta-suave">Clave de búsqueda</dt>
                            {/* Sigue siendo texto en Mam, así que va en Charis y no en
                                monoespaciada: aquí se comprueba que el saltillo desapareció y
                                que la `ẍ` sobrevivió. */}
                            <dd className="mam break-all" lang="mam">{entrada.busqueda}</dd>
                        </div>
                        <div>
                            <dt className="text-tinta-suave">Clave de orden</dt>
                            <dd className="font-mono break-all">
                                {enPares(entrada.orden_alfabetico)}
                            </dd>
                        </div>
                    </dl>

                    <p className="text-xs text-tinta-suave">
                        Las calcula el sistema a partir de la palabra en Mam y no se pueden editar. La
                        clave de orden va por pares: cada uno es la posición de una letra en el
                        alfabeto, y los dígrafos y las glotalizadas cuentan como una sola. Si el orden
                        parece equivocado, hay que reportarlo, no corregirlo a mano.
                    </p>

                    {entrada.creado_por != null && (
                        <p className="text-xs text-tinta-suave">Cargada por {entrada.creado_por.nombre}.</p>
                    )}
                </div>
            )}
        </div>
    );
}
