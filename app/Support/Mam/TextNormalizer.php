<?php

declare(strict_types=1);

namespace App\Support\Mam;

use Normalizer;
use RuntimeException;

/**
 * Saneamiento del texto en Mam. Ningún texto entra a la base sin pasar por aquí.
 *
 * Métodos estáticos y sin dependencias a propósito: lo llaman el mutator del modelo, los seeders,
 * los comandos de importación y tinker. Una función pura no gana nada con ir por el contenedor.
 *
 * Lo que corrige está documentado en `.claude/rules/ortografia-mam.md`: los PDF de origen
 * tipografían `ẍ` como `õ` (fuentes anteriores a Unicode), y reparten los apóstrofos entre cuatro
 * puntos de código distintos —el COLIMAM trae 5,152 U+0027 contra 181 U+2019—. Ninguna de las dos
 * corrupciones se ve en pantalla.
 */
final class TextNormalizer
{
    /** U+2019. El único apóstrofo válido del proyecto, y el grafema 32 cuando va solo. */
    public const APOSTROPHE = "\u{2019}";

    /** U+1E8D / U+1E8C. `ẍ`, el grafema 30. */
    private const X_DIERESIS = "\u{1E8D}";

    /**
     * La corrupción tipográfica de los PDF: el glifo de `ẍ` vivía en la posición de `õ`.
     * `õ` no existe en el alfabeto Mam, así que toda aparición es un error, sin excepciones.
     */
    private const CORRUPTED = [
        "\u{00F5}" => self::X_DIERESIS,   // õ → ẍ
        "\u{00D5}" => "\u{1E8C}",         // Õ → Ẍ
    ];

    /** Apóstrofo recto del teclado, letra modificadora y saltillo latino. Todos a U+2019. */
    private const APOSTROPHES = ["\u{0027}", "\u{02BC}", "\u{A78C}"];

    private function __construct() {}

    /**
     * NFC → `õ`→`ẍ` → apóstrofos a U+2019 → NFC. Idempotente.
     */
    public static function normalize(string $text): string
    {
        // NFC va primero, no al final como sugiere el archivo de reglas. Si la corrupción llega
        // descompuesta (`o` + U+0303, y el corpus trae texto descompuesto: por eso existe el
        // vector `x`+U+0308 → `ẍ`), la sustitución no la ve y el NFC posterior la recompondría
        // a `õ`, que sobreviviría intacta hasta la base. Componer primero deja toda la corrupción
        // en forma precompuesta antes de sustituirla.
        $text = self::toNfc($text);

        $text = strtr($text, self::CORRUPTED);

        // `str_replace` aquí es seguro: se sustituye el apóstrofo entero, nunca una letra suelta.
        // Lo que rompería los dígrafos es tocar la `t` de `tz’` o la `c` de `ch`.
        $text = str_replace(self::APOSTROPHES, self::APOSTROPHE, $text);

        // Las sustituciones solo introducen U+1E8D, U+1E8C y U+2019, estables en NFC, pero una
        // marca combinante que quedara detrás de la letra sustituida sí podría recomponer.
        return self::toNfc($text);
    }

    /**
     * Lo que alimenta la columna `busqueda`: minúsculas, sin saltillos, `ẍ`→`x` y sin diacríticos
     * del castellano. Ningún estudiante teclea el saltillo ni la diéresis al buscar.
     */
    public static function searchKey(string $text): string
    {
        $text = mb_strtolower(self::normalize($text), 'UTF-8');

        // Explícito, aunque el barrido de marcas de más abajo lo lograría igual: el grafema 30 no
        // depende de un detalle de implementación del barrido.
        $text = str_replace(self::X_DIERESIS, 'x', $text);

        $text = str_replace(self::APOSTROPHE, '', $text);

        // Diacríticos del castellano: las definiciones, los ejemplos y las traducciones vienen en
        // español. Descomponer, borrar las marcas y volver a componer.
        $decomposed = self::normalizeTo($text, Normalizer::FORM_D);

        return self::toNfc(preg_replace('/\p{Mn}+/u', '', $decomposed) ?? '');
    }

    private static function toNfc(string $text): string
    {
        return self::normalizeTo($text, Normalizer::FORM_C);
    }

    /**
     * `Normalizer::normalize()` solo devuelve `false` cuando la entrada no es UTF-8 válido. Eso no
     * es un carácter raro que se pueda tolerar: es una secuencia de bytes rota, y devolver el
     * texto tal cual la dejaría entrar a la base. Preferible reventar aquí.
     */
    private static function normalizeTo(string $text, int $form): string
    {
        if ($text === '') {
            return '';
        }

        $normalized = Normalizer::normalize($text, $form);

        if ($normalized === false) {
            throw new RuntimeException(
                'El texto no es UTF-8 válido y no se puede normalizar. Revisar la extracción del PDF de origen.'
            );
        }

        return $normalized;
    }
}
