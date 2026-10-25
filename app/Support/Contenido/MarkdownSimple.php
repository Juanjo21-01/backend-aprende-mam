<?php

declare(strict_types=1);

namespace App\Support\Contenido;

/**
 * Comprueba que el cuerpo de una página sea Markdown simple y no una puerta para marcado
 * ajeno.
 *
 * No renderiza nada: el Markdown se convierte a HTML en la compilación del sitio, en el
 * frontend. Aquí solo se rechaza, al guardar y con un motivo legible, lo que no debe llegar
 * hasta allá. Las tres cosas que se comprueban:
 *
 *  - **Nada de HTML.** Cualquier `<etiqueta>` falla. Incluye los autoenlaces `<https://…>`;
 *    para un docente la forma `[texto](https://…)` es la única que hace falta aprender.
 *  - **Enlaces solo a https o a rutas del sitio** (o a un ancla `#…`), ver `Enlaces`.
 *  - **Imágenes solo por URL https o ruta del sitio.** No se suben archivos.
 *
 * Es una comprobación de forma, no un analizador de Markdown: no entiende paréntesis
 * anidados en una URL ni bloques de código. Para el contenido de este sitio —cultura,
 * gramática, base legal— no hacen falta, y a cambio el código cabe en una pantalla.
 */
final class MarkdownSimple
{
    private function __construct() {}

    /**
     * Motivo por el que el cuerpo no es válido, o `null` si lo es.
     */
    public static function problema(string $cuerpo): ?string
    {
        if (preg_match('/<\s*[\/!?a-zA-Z]/', $cuerpo) === 1) {
            return 'no admite HTML. Escribe solo con Markdown: ## títulos, **negritas**, listas y [enlaces](https://…).';
        }

        // `![alt](destino)` y `[texto](destino)`. El `!` decide qué reglas aplican.
        preg_match_all('/(!?)\[[^\]]*\]\(\s*<?([^\s)>]*)/', $cuerpo, $enlaces, PREG_SET_ORDER);

        // Definiciones de referencia: `[id]: destino`.
        preg_match_all('/^ {0,3}\[[^\]]+\]:\s*<?(\S+)/m', $cuerpo, $referencias, PREG_SET_ORDER);

        foreach ($enlaces as [, $imagen, $destino]) {
            if ($imagen === '!' && ! Enlaces::esDestinoSeguro($destino)) {
                return "tiene una imagen con una dirección que no se admite («{$destino}»). Usa una dirección https o una ruta del sitio que empiece con «/».";
            }

            if ($imagen === '' && ! Enlaces::esDestinoSeguro($destino, permiteAncla: true)) {
                return "tiene un enlace con una dirección que no se admite («{$destino}»). Usa https, una ruta del sitio que empiece con «/» o un ancla «#».";
            }
        }

        foreach ($referencias as [, $destino]) {
            if (! Enlaces::esDestinoSeguro($destino, permiteAncla: true)) {
                return "tiene una referencia con una dirección que no se admite («{$destino}»). Usa https o una ruta del sitio que empiece con «/».";
            }
        }

        return null;
    }
}
