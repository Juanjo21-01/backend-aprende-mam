# AprendeMam — API y panel de administración

Biblioteca digital del idioma maya **Mam** (variante de San Marcos, Guatemala) para escuelas de primaria y básicos. Este repositorio es el backend headless: CMS, base de datos y endpoint de exportación. El sitio público vive en otro repositorio (Astro) y **no consume esta API en tiempo de ejecución**.

## Contexto que no está en el código

- Los usuarios finales son estudiantes y docentes de San Marcos con conexión 3G o sin señal.
- El contenido proviene de materiales de MINEDUC, DIGEBI y la Academia de Lenguas Mayas de Guatemala (ALMG). Cada entrada conserva su fuente bibliográfica.
- Es el componente práctico de un proyecto de graduación de Licenciatura en Educación. La trazabilidad del contenido importa tanto como que el sistema funcione.
- Lo mantiene una sola persona. Prioriza simplicidad sobre sofisticación.

## Ortografía del Mam — reglas innegociables

Romper estas reglas corrompe los datos de una forma que **no se nota en pantalla**. La especificación completa y los vectores de prueba están en `.claude/rules/ortografia-mam.md`.

- El alfabeto tiene **32 grafemas**. Los dígrafos (`ch`, `ky`, `tx`, `tz`) y las glotalizadas (`b'`, `ch'`, `k'`, `ky'`, `q'`, `t'`, `tx'`, `tz'`) son **una sola letra**, nunca una secuencia de dos.
- Todas las tablas y columnas en `utf8mb4` con cotejamiento `utf8mb4_unicode_ci`. Sin excepción, declarado explícitamente en cada migración.
- **Ningún texto en Mam entra a la base sin pasar por el normalizador.** Se aplica en un mutator del modelo, nunca en el controlador ni en el FormRequest, para que también cubra seeders, comandos de importación y tinker.
- Apóstrofo canónico: **U+2019**. Convertir U+0027, U+02BC y U+A78C a U+2019 al guardar.
- Normalizar siempre a **NFC**: `Normalizer::normalize($v, Normalizer::FORM_C)`.
- La letra `ẍ` (U+1E8D) llega corrupta como `õ` (U+00F5) desde los PDF de origen. Sustituir `õ`→`ẍ` y `Õ`→`Ẍ`. **`õ` no existe en el alfabeto Mam: toda aparición es un error.**
- `orden_alfabetico` y `busqueda` son **columnas derivadas**. Se recalculan en el mutator cada vez que cambia el campo `mam`. Nunca se aceptan desde el request ni se editan a mano.
- **Nunca usar `ORDER BY mam`.** El orden alfabético del Mam no es el del castellano. Siempre `ORDER BY orden_alfabetico`.

## Decisiones de arquitectura y su porqué

- **Headless con exportación estática.** El sitio público se compila y se sirve como archivos estáticos desde un CDN. Esta API solo la consumen el administrador y el proceso de build. Motivo: el alojamiento es un cPanel compartido y el tráfico de estudiantes lo tumbaría.
- **Endpoint de exportación versionado y de solo lectura.** Devuelve el conjunto completo de vocabulario más un número de versión. Protegido por token.
- **La publicación se dispara con debounce.** Al guardar contenido se encola un job con retardo que se reemplaza a sí mismo. Motivo: cargar 40 palabras debe producir un build, no cuarenta.
- **`municipio` y `fuente_id` son nullable pero existen.** El proyecto cubre solo San Marcos, pero la trazabilidad bibliográfica es requisito académico y el campo cuesta cero hoy.
- **Toda entrada nace con `revisado = false`.** Solo el validador lingüístico la marca como revisada. Permite cargar rápido y validar después sin perder el rastro.

## Autenticación y API

- **Panel de administración:** Laravel Sanctum con autenticación de sesión
  (no tokens de API). Solo hay dos roles: administrador y editor.
- **Endpoint de exportación:** protegido por token estático en `.env`. Lo consume
  únicamente el proceso de build del frontend.
- **No hay autenticación de usuarios finales.** El sitio público no tiene registro.
  Si aparece una ruta de login para estudiantes, es un error.
- Limitación de intentos de acceso en el login del panel.

## Convenciones

- PHP 8.3+ (requisito de Laravel 13), Laravel 13, PSR-12.
- Toda la lógica ortográfica vive en `app/Support/Mam/`. No dispersarla en modelos, helpers sueltos ni traits.
- **Los tests de ortografía se escriben antes que la implementación.** Los vectores de prueba ya están en el archivo de reglas.
- Nombres de tablas y columnas en español (es el dominio del proyecto); código, clases y métodos en inglés.
- Las migraciones declaran charset y collation de forma explícita, sin depender de la configuración por defecto del servidor.

## Qué no hacer

- No instalar paquetes que no sean estrictamente necesarios.
- No exponer endpoints públicos de consulta: rompe el modelo de despliegue y mete tráfico al cPanel.
- No implementar cuentas de estudiante. El sitio público no tiene registro ni recoge datos personales, porque los usuarios son menores de edad.
- No confiar en el cotejamiento de MySQL para ordenar texto en Mam.
- No usar `str_replace` sobre texto en Mam sin considerar los dígrafos: reemplazar `t` puede romper `tz'`.
