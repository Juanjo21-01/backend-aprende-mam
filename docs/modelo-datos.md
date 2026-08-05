# Modelo de datos — referencia

> Documento de referencia. **No se carga automáticamente en las sesiones de Claude Code.**
> Consultarlo explícitamente cuando se trabaje en migraciones o modelos:
> «leé `docs/modelo-datos.md` antes de crear las migraciones».
>
> Es un punto de partida derivado del análisis del corpus documental, no una especificación
> cerrada. Ajustar según se descubran necesidades reales durante la ingesta.

Todas las tablas: `utf8mb4` / `utf8mb4_unicode_ci`, declarado explícitamente en la migración.

## Núcleo léxico

### `grafemas`

Catálogo del alfabeto. Lo consume el tokenizador; no se codifica en constantes.

| Campo        | Tipo       | Notas                                                                 |
| ------------ | ---------- | --------------------------------------------------------------------- |
| `posicion`   | tinyint    | 1–32, orden de intercalación                                          |
| `grafema`    | varchar(3) | `a`, `b'`, `tz'`, `ẍ`, `'`                                            |
| `tipo`       | enum       | vocal, consonante_simple, consonante_compuesta, glotalizada, saltillo |
| `es_digrafo` | bool       |                                                                       |

### `entradas`

Entidad central del diccionario.

| Campo                     | Tipo                | Notas                                             |
| ------------------------- | ------------------- | ------------------------------------------------- |
| `mam`                     | varchar             | Forma de despliegue, normalizada                  |
| `espanol`                 | text                | Traducción; separar acepciones con punto y coma   |
| `definicion`              | text                | Definición extendida (viene del COLIMAM)          |
| `categoria_gramatical_id` | fk                  |                                                   |
| `busqueda`                | varchar, indexada   | **Derivada.** Minúsculas, sin apóstrofos, `ẍ`→`x` |
| `orden_alfabetico`        | varchar, indexada   | **Derivada.** Clave de intercalación              |
| `municipio`               | varchar, nullable   | Variación intradepartamental                      |
| `fuente_id`               | fk, nullable        | Trazabilidad bibliográfica                        |
| `pagina_fuente`           | varchar, nullable   |                                                   |
| `revisado`                | bool, default false | Solo lo cambia el validador lingüístico           |
| `imagen_id` / `audio_id`  | fk, nullable        |                                                   |

### `formas`

Formas alternativas de una entrada. Necesaria por la morfología del Mam: los sustantivos
tienen forma absoluta y poseída, y algunos son supletivos (cambian de raíz al poseerse).

`entrada_id`, `tipo_forma` (absoluta / poseída / supletiva), `forma`, `notas`

### `categorias_gramaticales`

Catálogo obtenido del corpus ALMG. **Conservar las clases propias de las lenguas mayas.**

| Abrev. | Clase              |     | Abrev.                   | Clase                              |
| ------ | ------------------ | --- | ------------------------ | ---------------------------------- |
| `s.`   | sustantivo         |     | `part.`                  | partícula                          |
| `v.t.` | verbo transitivo   |     | `med.`                   | medida                             |
| `adj.` | adjetivo           |     | `num.`                   | numeral                            |
| `v.i.` | verbo intransitivo |     | `afect.`                 | afectivo                           |
| `pos.` | posicional         |     | `pron.`                  | pronombre                          |
| `adv.` | adverbio           |     | `clas.`                  | clasificador                       |
| `af.`  | afijo              |     | `dir.` / `dem.` / `nom.` | direccional, demostrativo, nominal |

### Resto del núcleo

- `categorias` — temas del vocabulario. Jerárquica (`padre_id`). `nombre_es`, `nombre_mam`, `slug`, `icono`, `orden`
- `entrada_categoria` — pivote. Una palabra puede estar en varios temas
- `sinonimos` — autorreferencial: `entrada_id`, `sinonimo_entrada_id`
- `fuentes` — `titulo`, `institucion`, `anio`, `licencia`, `url`

## Contenido pedagógico y cultural

Estructuras presentes en el corpus que el plan inicial no contemplaba:

| Tabla                         | Contenido                                                                | Origen                                                                |
| ----------------------------- | ------------------------------------------------------------------------ | --------------------------------------------------------------------- |
| `ejemplos`                    | Oraciones de ejemplo con traducción                                      | Secciones «Qo yolin tnejil qyol» del módulo L2 y ejemplos del COLIMAM |
| `dialogos` + `turnos_dialogo` | Diálogos con hablante, orden y traducción                                | Capítulo «Qo yolin / Platiquemos»                                     |
| `textos`                      | Trabalenguas, adivinanzas, canciones, poemas, cuentos. Tipado por `tipo` | Capítulo «Leamos y escribamos en mam»                                 |
| `textos.respuesta`            | Respuesta de las adivinanzas                                             | Insumo directo para un juego                                          |
| `textos.preguntas`            | Comprensión lectora de los cuentos                                       | Insumo para cuestionarios                                             |
| `frases_ancestrales`          | Consejos y advertencias tradicionales                                    | Sección «Kykawb'il qchman»                                            |
| `numerales`                   | 1 al 20 con valor y representación maya                                  | Sección «Qo ajlan toj qyol Mam»                                       |
| `paginas`                     | Contenido editorial de secciones culturales                              | Módulo 1                                                              |
| `documentos`                  | PDF descargables, metadatos, contador de descargas                       | Módulo 3                                                              |

Los numerales mayas se renderizan como SVG en el frontend: el bloque Unicode existe pero
no tiene soporte tipográfico real.

## Gamificación

- `cuestionarios` — `titulo`, `categoria_id`, `dificultad`, `publicado`
- `preguntas` — `cuestionario_id`, `enunciado`, `tipo`, `orden`, `retroalimentacion`
- `opciones` — `pregunta_id`, `texto`, `es_correcta`, `imagen_id`
- `juegos_externos` — `titulo`, `proveedor`, `codigo_embebido`, `categoria_id`, `activo`
