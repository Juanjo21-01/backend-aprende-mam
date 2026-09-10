# Paleta y accesibilidad del panel de administración

Los tonos son los del sitio público (`src/styles/global.css` del repo de Astro) con las
proporciones invertidas: allá el jade es banda ancha y el papel es el contenido; aquí el jade
es solo filo —la barra de arriba, el anillo de foco, la pestaña activa— y el papel ocupa el
resto.

Se declaran en `resources/css/app.css` y se repiten **a mano** en
`resources/views/admin/login.blade.php`, que no pasa por Vite a propósito. Si se toca un
tono, hay que tocarlo en los dos sitios.

## Las dos reglas que no son de gusto

1. **El oro significa una sola cosa: `revisado = true`.** Es decir, «esto llega al sitio
   público». Ningún otro elemento del panel es dorado. Por eso la pestaña activa de la
   navegación lleva filo blanco y no dorado, aunque el sitio público lo lleve dorado: gastar
   el oro en la navegación lo dejaría sin significado. La única excepción es la cenefa, que
   es textura de 7 px con `aria-hidden` y no informa de nada.
2. **No hay un segundo verde.** Lo correcto, lo publicado y la acción principal son jade. El
   `#15803d` que traía el esqueleto de Laravel se fue por eso.

## La banda no cambia con el tema

`--color-jade-hondo`, `--color-sobre-jade` y `--color-jade-claro` están fuera del bloque de
modo oscuro. La cabecera es verde oscuro en los dos temas, así que si su texto siguiera a
`--color-papel` se volvería casi negro sobre verde oscuro al pasar a oscuro: **2.5:1,
ilegible**. Fue el primer fallo que apareció al medir.

Por lo mismo `.boton` no puede oscurecer al pasar el ratón usando `jade-hondo`: en oscuro el
botón es verde claro con texto casi negro y oscurecerlo dejaría el texto invisible. De ahí
`--color-jade-hover` y `--color-alerta-hover`, que oscurecen en claro y aclaran en oscuro.

## Dos bordes, no uno

`--color-borde` da **1.39:1** contra la tarjeta. Para separar filas o cerrar una tarjeta está
bien: son adorno estructural y el criterio 1.4.11 de WCAG no los mira.

Pero el fondo de un campo (`papel`) y el de la tarjeta que lo contiene se diferencian en
**1.02:1**, o sea nada. En un campo de formulario ese borde **es lo único que dice que ahí hay
un control**, y entonces 1.4.11 sí lo mira y pide 3:1. De ahí `--color-borde-campo`, que lo
usan `.campo`, `.boton-secundario`, `.tecla-grafema` y el rombo sin rellenar. Todo lo demás
sigue con `--color-borde`.

## Contrastes medidos

AA pide 4.5:1 para texto normal, 3:1 para texto grande (≥18.66 px en negrita o ≥24 px) y 3:1
para el contorno de un control (1.4.11).

### Claro

| Par | Ratio | |
|---|---|---|
| tinta / papel — texto principal | 16.53:1 | AA |
| tinta / tarjeta | 16.96:1 | AA |
| tinta / papel-hondo — franja de publicación y tecla de grafema | 15.18:1 | AA |
| tinta-suave / papel — texto secundario | 7.72:1 | AA |
| tinta-suave / tarjeta | 7.92:1 | AA |
| tinta-suave / papel-hondo — chip de tema | 7.09:1 | AA |
| jade / papel — acento y anillo de foco | 5.89:1 | AA |
| jade / tarjeta | 6.04:1 | AA |
| alerta / papel — error y enlace de borrar | 7.12:1 | AA |
| alerta / tarjeta | 7.31:1 | AA |
| papel / jade — `.boton` y el salto al contenido | 5.89:1 | AA |
| papel / jade-hover — `.boton` al pasar | 9.21:1 | AA |
| papel / alerta — `.boton-peligro` | 7.12:1 | AA |
| papel / alerta-hover — `.boton-peligro` al pasar | 9.45:1 | AA |
| sobre-jade / jade-hondo — banda y pestaña activa | 9.21:1 | AA |
| jade-claro / jade-hondo — navegación inactiva y rol | 4.98:1 | AA |
| oro / jade-hondo — el «Mam» del logotipo, 20 px negrita | 3.87:1 | AA grande |
| oro-hondo / papel | 5.25:1 | AA |
| borde-campo / tarjeta — contorno de control | 3.55:1 | 1.4.11 |
| borde-campo / papel | 3.46:1 | 1.4.11 |
| borde-campo / papel-hondo | 3.18:1 | 1.4.11 |
| **oro / papel** | **2.38:1** | **no usar como texto** |
| borde / tarjeta — filetes y separadores | 1.39:1 | adorno, exento |

### Oscuro

| Par | Ratio | |
|---|---|---|
| tinta / papel — texto principal | 15.60:1 | AA |
| tinta / tarjeta | 14.50:1 | AA |
| tinta / papel-hondo | 13.55:1 | AA |
| tinta-suave / papel | 7.15:1 | AA |
| tinta-suave / tarjeta | 6.65:1 | AA |
| tinta-suave / papel-hondo | 6.21:1 | AA |
| jade / papel | 7.41:1 | AA |
| jade / tarjeta | 6.88:1 | AA |
| alerta / papel | 7.70:1 | AA |
| alerta / tarjeta | 7.16:1 | AA |
| papel / jade — `.boton` | 7.41:1 | AA |
| papel / jade-hover | 9.06:1 | AA |
| papel / alerta — `.boton-peligro` | 7.70:1 | AA |
| papel / alerta-hover | 9.44:1 | AA |
| sobre-jade / jade-hondo | 9.21:1 | AA |
| jade-claro / jade-hondo | 4.98:1 | AA |
| oro / jade-hondo — logotipo | 4.39:1 | AA grande |
| oro / papel | 8.68:1 | AA |
| oro-hondo / papel | 7.65:1 | AA |
| borde-campo / tarjeta | 3.55:1 | 1.4.11 |
| borde-campo / papel | 3.82:1 | 1.4.11 |
| borde-campo / papel-hondo | 3.31:1 | 1.4.11 |
| borde / tarjeta | 1.39:1 | adorno, exento |

El único par de texto que no llega es `oro / papel` en claro, y es a propósito: por eso la
marca de revisión es una **forma** —el rombo, `.marca-revision`— y la palabra «Revisada» va en
tinta al lado. Quien no distinga el dorado sigue leyendo la palabra.

## Objetivos táctiles

El criterio 2.5.8 de WCAG 2.2 pide 24×24 px. Medidos a partir del CSS:

| Elemento | Alto | |
|---|---|---|
| Enlace de sección de la navegación | 43 px | py-2.5 + 20 + filo de 3 |
| `.tecla-grafema` | 40 px | h-10 |
| `.campo` | 36 px | py-2 + 20 |
| `.boton`, `.boton-secundario`, `.boton-peligro` | 36 px | py-2 + 20 |
| `.boton-banda` | 32 px | py-1.5 + 20 |
| Resumen de la franja de publicación | 32 px | py-2 + 16 |
| `.casilla` — envoltorio de una casilla | 32 px | 16 + p-2 |
| Resumen de filtros | 28 px | py-1 + 20 |
| `.enlace-accion`, `.enlace-peligro` | 24 px | py-1 + 16 |

`.casilla` merece explicación: una casilla de 16 px es un objetivo de 16 px, y agrandarla a 24
la dejaría enorme al lado de una tabla de 12 px. Lo que crece es el área de pulsación y no el
dibujo —`p-2` la lleva a 32 px y `-m-2` devuelve el hueco—, así que la fila mide lo mismo que
antes. Va en una `<label>` y no en un `<div>` porque la etiqueta además da nombre a la casilla.

## Lo demás que se cuidó

- **`lang="mam"` en todo el Mam.** Va donde va `.mam`: el lema de la tabla y de la ficha, el
  campo `mam`, el `nombre_mam` de un tema, la clave de búsqueda, el teclado de grafemas y los
  avisos de corrección. Sin él, un lector de pantalla lee `tx’otx’` con las reglas del
  castellano.
- **Salto al contenido.** Antes del contenido hay siete paradas de tabulador que se repiten en
  cada pantalla. `<main>` lleva `tabIndex={-1}` para que el salto mueva el foco y no solo el
  desplazamiento.
- **Ningún `tabindex` positivo.** El orden de tabulación es el del documento.
- **Foco visible global**, en `@layer base`, para que ningún control nuevo nazca sin él.
- **`prefers-reduced-motion`** anulando transiciones y animaciones, aunque hoy lo único que se
  mueve sea el color de un botón.
- **Nada vive solo en un `title`.** Quedan tres: la posición de una letra en el teclado de
  grafemas y la consecuencia de firmar una revisión, ambas complementarias de un texto
  visible, y un `<abbr>` para la clase de palabra, que es el elemento que existe para eso.
- **Diálogo de confirmación nativo** (`<dialog>` con `showModal()`): foco atrapado, cierre con
  Escape y fondo inerte sin escribirlos a mano. El foco arranca en «Cancelar».

## Volver a medirlo

Sin dependencias. Guardar como `.mjs` y correr con `node`. Si se cambia un tono, se actualizan
las tablas de arriba con lo que salga.

```js
const L = h => { const c = [1,3,5].map(i => { const v = parseInt(h.slice(i,i+2),16)/255;
  return v <= 0.03928 ? v/12.92 : ((v+0.055)/1.055)**2.4; });
  return 0.2126*c[0]+0.7152*c[1]+0.0722*c[2]; };
const R = (a,b) => { const [x,y] = [L(a),L(b)].sort((p,q)=>q-p); return (x+0.05)/(y+0.05); };

// Los tres de la banda no cambian con el tema.
const banda = { jadeHondo:'#0a4f47', sobreJade:'#fdfcf9', jadeClaro:'#9dc4bd' };

const claro = { ...banda, papel:'#fdfcf9', papelHondo:'#f5f2ec', tarjeta:'#ffffff',
  tinta:'#1f1c19', tintaSuave:'#57504a', borde:'#e0dad0', bordeCampo:'#8f877a',
  jade:'#0f6f63', jadeHover:'#0a4f47', oro:'#d99a1e', oroHondo:'#90610c',
  alerta:'#9a3412', alertaHover:'#7a2a0e' };

const oscuro = { ...banda, papel:'#14120e', papelHondo:'#23201a', tarjeta:'#1c1a15',
  tinta:'#efeae0', tintaSuave:'#a89f90', borde:'#38332a', bordeCampo:'#787060',
  jade:'#4fb3a4', jadeHover:'#6ac4b5', oro:'#e0a733', oroHondo:'#d99a1e',
  alerta:'#ea8f6d', alertaHover:'#f2a68a' };

const pares = [
  ['tinta','papel'], ['tinta','tarjeta'], ['tinta','papelHondo'],
  ['tintaSuave','papel'], ['tintaSuave','tarjeta'], ['tintaSuave','papelHondo'],
  ['jade','papel'], ['jade','tarjeta'], ['alerta','papel'], ['alerta','tarjeta'],
  ['papel','jade'], ['papel','jadeHover'], ['papel','alerta'], ['papel','alertaHover'],
  ['sobreJade','jadeHondo'], ['jadeClaro','jadeHondo'],
  ['oro','jadeHondo'], ['oro','papel'], ['oroHondo','papel'],
  ['bordeCampo','tarjeta'], ['bordeCampo','papel'], ['bordeCampo','papelHondo'],
  ['borde','tarjeta'],
];

for (const [modo, p] of [['CLARO', claro], ['OSCURO', oscuro]]) {
  console.log(`\n=== ${modo} ===`);
  for (const [a, b] of pares) {
    const r = R(p[a], p[b]);
    console.log(`${(r >= 4.5 ? 'AA ' : r >= 3 ? '3:1' : 'NO ')} ${r.toFixed(2).padStart(5)}:1  ${a} / ${b}`);
  }
}
```

## Tipografía

Dos familias, repartidas por lo que el texto **es** y no por dónde está:

- **Charis SIL** (`.mam`, siempre con `lang="mam"`) para todo el Mam: el lema de una entrada,
  el campo `mam`, el `nombre_mam` de un tema, la clave de búsqueda, las teclas del teclado de
  grafemas. Los dos WOFF2 salen del recorte del repo de Astro (`scripts/subset-fonts.mjs`) y
  están copiados en `public/fonts`, 19 y 18 KB.
- **Instrument Sans** para la maquinaria: etiquetas, botones, navegación, ayudas.

No es decoración. Ninguna fuente de sistema trae `ẍ`, así que el navegador la sustituye por
otra familia a mitad de palabra y el editor ve un trazo que no es el que verá el estudiante.
El recorte ya incluye `Ẍẍ`, los tres apóstrofos (U+2019 canónico, U+0027 y U+02BC) y la `õÕ`
con que los PDF de origen corrompen la `ẍ`, que es justo lo que el panel necesita poder
distinguir a simple vista para que el aviso de «lo corregí al guardar» signifique algo.

La clave de orden va en monoespaciada y separada en pares: `01 14 01 02` son **cuatro** letras,
no ocho, porque los dígrafos y las glotalizadas ocupan una sola posición del alfabeto.

Nunca aplicar `text-transform` sobre texto en Mam: los dígrafos y las glotalizadas no se
mayusculizan letra por letra.
