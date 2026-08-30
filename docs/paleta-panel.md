# Paleta del panel de administración

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
`--color-jade-hover`, que oscurece en claro y aclara en oscuro.

## Contrastes medidos

AA pide 4.5:1 para texto normal y 3:1 para texto grande (≥18.66 px en negrita o ≥24 px).

### Claro

| Par | Ratio | |
|---|---|---|
| tinta / papel — texto principal | 16.53:1 | AA |
| tinta / tarjeta | 16.96:1 | AA |
| tinta / papel-hondo — franja de publicación | 15.18:1 | AA |
| tinta-suave / papel — texto secundario | 7.72:1 | AA |
| tinta-suave / tarjeta | 7.92:1 | AA |
| tinta-suave / papel-hondo | 7.09:1 | AA |
| jade / papel — acento y foco | 5.89:1 | AA |
| jade / tarjeta | 6.04:1 | AA |
| alerta / papel — error | 7.12:1 | AA |
| alerta / tarjeta | 7.31:1 | AA |
| papel / jade — texto de `.boton` | 5.89:1 | AA |
| papel / jade-hover — `.boton` al pasar el ratón | 9.21:1 | AA |
| sobre-jade / jade-hondo — banda y pestaña activa | 9.21:1 | AA |
| jade-claro / jade-hondo — navegación inactiva y rol | 4.98:1 | AA |
| oro / jade-hondo — el «Mam» del logotipo, 20 px negrita | 3.87:1 | AA grande |
| oro-hondo / papel | 5.25:1 | AA |
| **oro / papel** | **2.38:1** | **no usar como texto** |

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
| papel / jade — texto de `.boton` | 7.41:1 | AA |
| papel / jade-hover | 9.06:1 | AA |
| sobre-jade / jade-hondo | 9.21:1 | AA |
| jade-claro / jade-hondo | 4.98:1 | AA |
| oro / jade-hondo — logotipo | 4.39:1 | AA |
| oro-hondo / papel | 7.65:1 | AA |

El único par que no llega es `oro / papel` en claro, y es a propósito: por eso la marca de
revisión es una **forma** —el rombo de la cenefa, `.marca-revision`— y la palabra «Revisada»
va en tinta al lado. Quien no distinga el dorado sigue leyendo la palabra. Si alguna vez hace
falta oro como texto sobre papel, el tono es `--color-oro-hondo`.

## Volver a medirlo

Sin dependencias. Guardar como `.mjs` y correr con `node`. Si se cambia un tono, se actualiza
la tabla de arriba con lo que salga.

```js
const L = h => { const c = [1,3,5].map(i => { const v = parseInt(h.slice(i,i+2),16)/255;
  return v <= 0.03928 ? v/12.92 : ((v+0.055)/1.055)**2.4; });
  return 0.2126*c[0]+0.7152*c[1]+0.0722*c[2]; };
const R = (a,b) => { const [x,y] = [L(a),L(b)].sort((p,q)=>q-p); return (x+0.05)/(y+0.05); };

// Los tres de la banda no cambian con el tema.
const banda = { jadeHondo:'#0a4f47', sobreJade:'#fdfcf9', jadeClaro:'#9dc4bd' };

const claro = { ...banda, papel:'#fdfcf9', papelHondo:'#f5f2ec', tarjeta:'#ffffff',
  tinta:'#1f1c19', tintaSuave:'#57504a', borde:'#e0dad0', jade:'#0f6f63',
  jadeHover:'#0a4f47', oro:'#d99a1e', oroHondo:'#90610c', alerta:'#9a3412' };

const oscuro = { ...banda, papel:'#14120e', papelHondo:'#23201a', tarjeta:'#1c1a15',
  tinta:'#efeae0', tintaSuave:'#a89f90', borde:'#38332a', jade:'#4fb3a4',
  jadeHover:'#6ac4b5', oro:'#e0a733', oroHondo:'#d99a1e', alerta:'#ea8f6d' };

const pares = [
  ['tinta','papel'], ['tinta','tarjeta'], ['tinta','papelHondo'],
  ['tintaSuave','papel'], ['tintaSuave','tarjeta'], ['tintaSuave','papelHondo'],
  ['jade','papel'], ['jade','tarjeta'], ['alerta','papel'], ['alerta','tarjeta'],
  ['papel','jade'], ['papel','jadeHover'],
  ['sobreJade','jadeHondo'], ['jadeClaro','jadeHondo'],
  ['oro','jadeHondo'], ['oro','papel'], ['oroHondo','papel'],
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

- **Charis SIL** (`.mam`) para todo el Mam: el lema de una entrada, el campo `mam`, el
  `nombre_mam` de un tema, las claves derivadas. Los dos WOFF2 salen del recorte del repo de
  Astro (`scripts/subset-fonts.mjs`) y están copiados en `public/fonts`, 19 y 18 KB.
- **Instrument Sans** para la maquinaria: etiquetas, botones, navegación, ayudas.

No es decoración. Ninguna fuente de sistema trae `ẍ`, así que el navegador la sustituye por
otra familia a mitad de palabra y el editor ve un trazo que no es el que verá el estudiante.
El recorte ya incluye `Ẍẍ`, los tres apóstrofos (U+2019 canónico, U+0027 y U+02BC) y la `õÕ`
con que los PDF de origen corrompen la `ẍ`, que es justo lo que el panel necesita poder
distinguir a simple vista para que el aviso de «lo corregí al guardar» signifique algo.

Nunca aplicar `text-transform` sobre texto en Mam: los dígrafos y las glotalizadas no se
mayusculizan letra por letra.
