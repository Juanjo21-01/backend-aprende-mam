---
paths:
  - "app/Support/Mam/**"
  - "app/Models/**"
  - "database/migrations/**"
  - "database/seeders/**"
  - "tests/**"
---

# Ortografía del Mam — especificación e implementación

## Alfabeto en orden de intercalación

```
01 a     02 b'    03 ch    04 ch'   05 e     06 i     07 j     08 k
09 k'    10 ky    11 ky'   12 l     13 m     14 n     15 o     16 p
17 q     18 q'    19 r     20 s     21 t     22 t'    23 tx    24 tx'
25 tz    26 tz'   27 u     28 w     29 x     30 ẍ     31 y     32 '
```

El saltillo (`'`) ocupa la última posición. Este orden se persiste como catálogo en base de datos, no como constante en código, para que un ajuste ratificado por COLIMAM no obligue a tocar la implementación.

## Tokenización

Coincidencia más larga primero, en este orden estricto:

1. Tres caracteres: `ch'` `ky'` `tx'` `tz'`
2. Dos caracteres: `b'` `k'` `q'` `t'` `ch` `ky` `tx` `tz`
3. Un carácter: el resto del alfabeto

### La regla del apóstrofo

Es la parte que se equivoca siempre. El apóstrofo cumple **dos funciones distintas** según el carácter que lo precede:

- Después de **consonante** → forma parte de la glotalizada y es inseparable de ella: `b'`, `tz'`, `ky'`
- Después de **vocal** → es el saltillo, un grafema autónomo en la posición 32: `jwe'`, `a'`, `pa'ch`

El tokenizador resuelve la ambigüedad mirando el carácter anterior. Sin esta regla, el orden alfabético sale mal y la segmentación de palabras es incorrecta.

## Clave de ordenamiento

Cada grafema se sustituye por su posición en dos dígitos y se concatena. El resultado se guarda en `orden_alfabetico`, que va indexada.

```
tz'is  → [tz'][i][s]  → 26 06 20  → "260620"
chmol  → [ch][m][o][l] → 03 13 15 12 → "03131512"
```

## Clave de búsqueda

La columna `busqueda` es contra la que corre el buscador. Se genera: minúsculas, sin apóstrofos, `ẍ`→`x`, sin diacríticos del castellano. Motivo: ningún estudiante escribe el saltillo ni la diéresis al buscar.

## Vectores de prueba

### Tokenización

```
tz'is       → [tz'] [i] [s]
tx'otx'     → [tx'] [o] [tx']
ky'aq       → [ky'] [a] [q]
q'aq'       → [q'] [a] [q']
b'ib'itz    → [b'] [i] [b'] [i] [tz]
kyiẍ        → [ky] [i] [ẍ]
chmol       → [ch] [m] [o] [l]
tx'ajol     → [tx'] [a] [j] [o] [l]
jwe'        → [j] [w] [e] [']          ← saltillo tras vocal
a'witz      → [a] ['] [w] [i] [tz]     ← saltillo tras vocal
pa'ch       → [p] [a] ['] [ch]         ← saltillo tras vocal, luego dígrafo
xq'exwi'    → [x] [q'] [e] [x] [w] [i] [']
```

### Ordenamiento

```
Entrada:  chmol, b'aq, tz'is, jal, ch'el, kyaje, tzaj, k'um, ẍiky, xaq
Esperado: b'aq, chmol, ch'el, jal, k'um, kyaje, tzaj, tz'is, xaq, ẍiky
```

Verificación de posiciones: `b'`=02 → `ch`=03 → `ch'`=04 → `j`=07 → `k'`=09 → `ky`=10 → `tz`=25 → `tz'`=26 → `x`=29 → `ẍ`=30.

Nótese que `chmol` va **antes** que `ch'el`, y que `xaq` va antes que `ẍiky`. En castellano ambos órdenes serían distintos.

### Normalización

```
"q'aq'"  con U+0027   → "q'aq'"  con U+2019
"q'aq'"  con U+02BC   → "q'aq'"  con U+2019
"õiky"                → "ẍiky"
"MOÕ"                 → "MOẌ"
"x" + U+0308          → "ẍ" (U+1E8D, precompuesta)
```

### Clave de búsqueda

```
mam         →  busqueda
q'aq'       →  qaq
ẍiky        →  xiky
tx'otx'     →  txotx
kyiẍ        →  kyix
Xq'exwi'    →  xqexwi
b'ib'itz    →  bibitz
```

## Origen de la corrupción `õ`

Los PDF de referencia fueron tipografiados con fuentes anteriores a Unicode, en las que el glifo de `ẍ` se asignaba a la posición de `õ`. Medición sobre el corpus real:

| Documento | `ẍ` correcta | `ẍ` como `õ` | Apóstrofo U+2019 | Apóstrofo U+0027 |
|---|---|---|---|---|
| Diccionario COLIMAM (2011) | 0 | 106 (muestra) | 181 | 5,152 |
| Diccionario de Sinónimos (2016) | 5 | 106 | 4,180 | 76 |
| Gramática Pedagógica (2018) | 90 | 6 | 3,264 | 59 |

Ninguna fuente es internamente consistente. El saneamiento se aplica en este orden: `õ`→`ẍ`, luego unificación de apóstrofos, luego NFC, luego generación de columnas derivadas.

## Importación del diccionario COLIMAM

El PDF tiene capa de texto y declara 6,185 entradas con estructura regular:

```
LEMA || cat. Definición en castellano. Ejemplo en Mam. Traducción del ejemplo.
```

El delimitador `||` permite parsear con expresión regular. Una extracción sin optimizar recupera ~4,914 entradas (79%); el resto se pierde por el flujo de dos columnas y se recupera con extracción posicional.

Taxonomía de clases de palabra usada por ALMG, con frecuencias observadas:

```
s.     sustantivo            2110    part.   partícula        58
v.t.   verbo transitivo       761    med.    medida           58
adj.   adjetivo               393    num.    numeral          35
v.i.   verbo intransitivo     306    afect.  afectivo         19
pos.   posicional             136    pron.   pronombre        11
adv.   adverbio               134    clas.   clasificador     10
af.    afijo                  113    dir./dem./nom.           23
```

`pos.` (posicional), `afect.` (afectivo), `dir.` (direccional) y `clas.` (clasificador) son clases propias de las lenguas mayas sin equivalente en castellano. **Conservarlas tal cual; no mapearlas a categorías del español.**
