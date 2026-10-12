<?php

declare(strict_types=1);

/**
 * Extrae el CSV intermedio del PDF «Neologismo / Vocabulario pedagógico Mam».
 *
 * Es un script de un solo uso, versionado para que el CSV se pueda reconstruir desde el PDF y
 * quede escrito cómo se obtuvo. No forma parte de la aplicación: no arranca Laravel y solo
 * toma del proyecto el normalizador ortográfico, para no duplicar sus reglas.
 *
 *     php database/data/neologismos/extraer.php <neologismos.pdf> <colimam.pdf> [salida.csv]
 *
 * Necesita `pdftotext` (xpdf, viene con Git for Windows) en el PATH. Los PDF no se versionan:
 * son material de terceros. El de neologismos circula como `725945284-NEOLOGISMO-
 * VOCABULARIO-PEDAGOGICO-MAM.pdf` (el número parece un id de Scribd; autor y año
 * desconocidos). El COLIMAM es el «Diccionario bilingüe Mam–Español» de la Comunidad
 * Lingüística Mam (ALMG).
 *
 * ## Cómo se leen las dos columnas
 *
 * El PDF es una tabla castellano → Mam, y ninguno de los dos modos de `pdftotext` basta solo:
 *
 * - `-raw` respeta el orden interno del documento, que es el bueno para emparejar, pero pega
 *   las dos celdas con un solo espacio y no dice dónde termina el castellano.
 * - `-layout` separa las columnas por posición, pero desplaza filas al dibujarlas: en la
 *   p. 173 empareja «Ser» con `Muqul`.
 *
 * Así que se toma el orden de `-raw` y se usa `-layout` solo como diccionario de celdas: una
 * línea de `-raw` se parte donde el trozo izquierdo es una celda de castellano conocida y el
 * derecho una de Mam. Las celdas partidas en varias líneas se reconstruyen con el mismo
 * criterio. Lo que no se pueda resolver con seguridad sale con estado `revisar`.
 *
 * ## Lo que el script no decide
 *
 * `tema`, `tema_origen` y el estado `frase` se asignan después, leyendo cada fila. La columna
 * `colimam` es solo una ayuda para el validador: el importador no la lee.
 */

require __DIR__.'/../../../vendor/autoload.php';

use App\Support\Mam\TextNormalizer;

/**
 * Tramos donde la columna Mam está corrida una fila respecto al castellano **en la propia
 * fuente** (Sesenta → «Kajb’lal», cuando sesenta es `oxk’al`, que viene en la fila de abajo).
 *
 * Se midieron cruzando cada par con el COLIMAM: en estas páginas el emparejamiento con la
 * fila siguiente acierta más que el directo, y en el resto del documento no. Las filas se
 * marcan para corregirlas a mano mirando el PDF; realinearlas aquí arriesgaría pares falsos
 * en los bordes del tramo, que no se notan en pantalla.
 */
const DRIFTED_PAGES = [[113, 117], [173, 182]];

const COLUMNS = ['mam', 'espanol', 'pagina', 'tema', 'tema_origen', 'estado', 'notas', 'colimam'];

/** El apóstrofo y la `ẍ` (o su corrupción `õ`) solo existen en Mam. */
const MAM_LETTERS = '/[’\'ẍẌõÕ]/u';

/** Las tildes y la `ñ` solo existen en castellano. */
const SPANISH_LETTERS = '/[áéíóúüñÁÉÍÓÚÑ]/u';

const STOPWORDS = ['de', 'la', 'el', 'los', 'las', 'un', 'una', 'y', 'o', 'en', 'a', 'con', 'por',
    'para', 'del', 'al', 'se', 'que', 'su', 'sus', 'es', 'lo', 'le', 'como', 'sin', 'otro', 'otra'];

if ($argc < 3) {
    fwrite(STDERR, "Uso: php extraer.php <neologismos.pdf> <colimam.pdf> [salida.csv]\n");
    exit(1);
}

$output = $argv[3] ?? __DIR__.'/neologismos.csv';

$raw = pdftotext($argv[1], '-raw');
$layout = pdftotext($argv[1], '-layout');
$colimam = colimamGlosses(pdftotext($argv[2], '-raw'));

$cells = layoutCells($layout);
[$records, $warnings] = parseRecords($raw, $cells);

$rows = [];
$summary = ['registros' => count($records), 'ok' => 0, 'revisar' => 0, 'coincide' => 0, 'difiere' => 0];

foreach ($records as $record) {
    $reasons = $record['reasons'];

    foreach (DRIFTED_PAGES as [$from, $to]) {
        if ($record['page'] >= $from && $record['page'] <= $to) {
            $reasons[] = "posible desfase de filas en la fuente (p. {$from}–{$to})";
        }
    }

    $variants = array_values(array_filter(array_map('trim', explode(',', $record['mam']))));

    if ($variants === []) {
        $variants = [''];
        $reasons[] = 'sin Mam';
    }

    foreach ($variants as $variant) {
        $mam = TextNormalizer::normalize($variant);
        $variantReasons = $reasons;

        // Lo que seguro no es del alfabeto: tildes, d, f, g, v, ñ, dígitos, paréntesis… El
        // importador haría fallar la tanda entera por una sola fila así, de modo que se aparta
        // aquí. Lo fino (una `b` sin saltillo) lo sigue atrapando el importador con el
        // tokenizador, que conoce el catálogo de grafemas.
        if ($mam !== '' && preg_match('/[^a-zẍ’ \-]/u', mb_strtolower($mam)) === 1) {
            $variantReasons[] = 'caracteres fuera del alfabeto Mam';
        }

        $state = $variantReasons === [] ? 'ok' : 'revisar';
        $check = colimamCheck($colimam, $mam, $record['es']);

        $summary[$state]++;

        if ($check !== '') {
            $summary[$check]++;
        }

        $rows[] = [$mam, $record['es'], (string) $record['page'], '', '', $state, implode('; ', array_unique($variantReasons)), $check];
    }
}

$file = fopen($output, 'w');
fputcsv($file, COLUMNS, escape: '');

foreach ($rows as $row) {
    fputcsv($file, $row, escape: '');
}

fclose($file);

foreach ($warnings as $warning) {
    fwrite(STDERR, "aviso: {$warning}\n");
}

printf(
    "%d registros → %d filas (%d ok, %d revisar). COLIMAM: %d coinciden, %d difieren.\nEscrito en %s\n",
    $summary['registros'],
    count($rows),
    $summary['ok'],
    $summary['revisar'],
    $summary['coincide'],
    $summary['difiere'],
    $output,
);

/** Texto del PDF en UTF-8, con salto de página `\f` entre páginas. */
function pdftotext(string $pdf, string $mode): string
{
    if (! is_file($pdf)) {
        fwrite(STDERR, "No se encuentra {$pdf}\n");
        exit(1);
    }

    $target = (string) tempnam(sys_get_temp_dir(), 'pdf');
    exec(sprintf('pdftotext -enc UTF-8 %s %s %s', $mode, escapeshellarg($pdf), escapeshellarg($target)), $out, $code);

    if ($code !== 0) {
        fwrite(STDERR, "pdftotext falló ({$code}) con {$pdf}\n");
        exit(1);
    }

    $text = (string) file_get_contents($target);
    unlink($target);

    return $text;
}

/**
 * Las celdas que `-layout` dibuja en cada columna, como conjuntos para buscar.
 *
 * La columna de castellano empieza en el margen; la de Mam, tras dos o más espacios o en una
 * línea sangrada (continuación de una celda larga).
 *
 * Además de las celdas, devuelve cuántas veces sale cada palabra en cada columna, contando solo
 * las filas que se separaron limpias: una línea sin hueco puede tener las columnas mezcladas.
 * Son frecuencias y no conjuntos porque `-layout` también se equivoca a veces al separar, y
 * una palabra suelta en la columna equivocada no debe cambiarle la lengua.
 *
 * @return array{left: array<string, true>, right: array<string, true>, loose: array<string, true>, es: array<string, int>, mam: array<string, int>}
 */
function layoutCells(string $layout): array
{
    $cells = ['left' => [], 'right' => [], 'loose' => [], 'es' => [], 'mam' => []];

    $vocabulary = function (string $column, string $text) use (&$cells): void {
        foreach (explode(' ', $text) as $token) {
            $word = wordKey($token);

            if ($word !== '') {
                $cells[$column][$word] = ($cells[$column][$word] ?? 0) + 1;
            }
        }
    };

    foreach (preg_split('/\R|\f/u', $layout) as $line) {
        if (trim($line) === '') {
            continue;
        }

        if (preg_match('/^\s/u', $line)) {
            $cells['right'][trim($line)] = true;
            $vocabulary('mam', trim($line));

            continue;
        }

        if (preg_match('/^(\S.*?)\s{2,}(\S.*)$/u', rtrim($line), $match)) {
            $cells['left'][trim($match[1])] = true;
            $cells['right'][trim($match[2])] = true;
            $vocabulary('es', $match[1]);
            $vocabulary('mam', $match[2]);

            continue;
        }

        $cells['loose'][trim($line)] = true;
    }

    return $cells;
}

/**
 * Reconstruye los registros (castellano, Mam, página) en el orden de `-raw`.
 *
 * @param  array{left: array<string, true>, right: array<string, true>, loose: array<string, true>, es: array<string, int>, mam: array<string, int>}  $cells
 * @return array{list<array{es: string, mam: string, page: int, reasons: list<string>}>, list<string>}
 */
function parseRecords(string $raw, array $cells): array
{
    $records = [];
    $warnings = [];
    $current = null;

    $close = function () use (&$current, &$records): void {
        if ($current !== null) {
            $records[] = [
                'es' => trim(implode(' ', $current['es'])),
                'mam' => trim(implode(' ', $current['mam'])),
                'page' => $current['page'],
                'reasons' => array_values(array_unique($current['reasons'])),
            ];
        }

        $current = null;
    };

    $open = function (int $page, string $es, array $reasons) use (&$current, $close): void {
        $close();
        $current = ['es' => [$es], 'mam' => [], 'page' => $page, 'reasons' => $reasons];
    };

    $pages = explode("\f", $raw);

    // El PDF tiene 372 páginas, pero la tabla termina en la 199. Después hay páginas sueltas
    // con una sola línea cada una («i’l, jitx’in»): colas recortadas de celdas que ya se
    // leyeron completas. Lo que quede pasada la última página con filas se descarta.
    $lastPage = 0;

    foreach ($pages as $index => $pageText) {
        $lines = array_filter(preg_split('/\R/u', $pageText), fn (string $line) => trim($line) !== '');

        if (count($lines) >= 5) {
            $lastPage = $index + 1;
        }
    }

    foreach ($pages as $index => $pageText) {
        $page = $index + 1;

        foreach (preg_split('/\R/u', $pageText) as $line) {
            $line = trim($line);

            if ($line === '' || $line === 'CASTELLANO MAM') {
                continue;
            }

            if ($page > $lastPage) {
                $warnings[] = "p. {$page}: fragmento suelto tras el final de la tabla, descartado: «{$line}»";

                continue;
            }

            [$kind, $es, $mam, $deduced] = classify($line, $cells);
            $reasons = $deduced ? ['columnas separadas por deducción: verificar el corte'] : [];

            if ($kind === 'es' || $kind === 'pair') {
                $text = $kind === 'es' ? $line : $es;
                $pending = $current !== null && $current['mam'] === [];
                $before = $pending ? implode(' ', $current['es']) : '';

                // Castellano tras otro castellano todavía sin Mam: es la misma celda partida
                // en varias líneas si la anterior quedó abierta («obedecer,», «(caserío de») o
                // esta empieza en minúscula («recibir»).
                $continues = $pending && (
                    str_ends_with($before, ',')
                    || substr_count($before, '(') > substr_count($before, ')')
                    || preg_match('/^[\p{Ll}(]/u', $text) === 1
                );

                if ($continues) {
                    $current['es'][] = $text;
                    $current['reasons'] = [...$current['reasons'], ...$reasons];
                } elseif ($pending && $kind === 'es' && $deduced) {
                    // Una línea que no es celda de castellano conocida, justo donde a la fila
                    // le falta el Mam: es su Mam, aunque no tenga apóstrofo que lo delate
                    // («Chupil», «Tzqix, sqix, sqixil»).
                    $current['mam'][] = $line;
                    $current['reasons'] = [...$current['reasons'], ...$reasons];

                    continue;
                } else {
                    $open($page, $text, $reasons);
                }

                if ($kind === 'pair') {
                    $current['mam'][] = $mam;
                }

                continue;
            }

            // Solo Mam.
            if ($current === null) {
                $warnings[] = "p. {$page}: línea de Mam sin castellano, descartada: «{$line}»";

                continue;
            }

            $previous = $current['mam'] === [] ? '' : $current['mam'][array_key_last($current['mam'])];
            $current['reasons'] = [...$current['reasons'], ...$reasons];

            if (str_ends_with($previous, '-')) {
                // «tal-» / «ab’aj»: palabra cortada con guion al final de la línea. El guion
                // también es ortografía del Mam (`t-xilen`), así que no se sabe si va o no.
                $current['mam'][array_key_last($current['mam'])] = $previous.$line;
                $current['reasons'][] = 'palabra cortada con guion al final de línea';

                continue;
            }

            // Una celda de Mam que sigue en otra línea casi siempre se corta tras una coma;
            // si no, la unión es dudosa.
            if ($previous !== '' && ! str_ends_with($previous, ',')) {
                $current['reasons'][] = 'celda de Mam partida en varias líneas';
            }

            $current['mam'][] = $line;
        }
    }

    $close();

    return [$records, $warnings];
}

/**
 * Qué es una línea de `-raw`: `es` (solo castellano), `mam` (solo Mam) o `pair` (las dos
 * celdas pegadas), con el corte y si hubo que deducirlo.
 *
 * Primero se busca la línea, o sus dos mitades, entre las celdas que `-layout` separó bien.
 * Si no aparece —`-layout` mezcló las columnas en esa fila—, se deduce el corte palabra por
 * palabra.
 *
 * @param  array{left: array<string, true>, right: array<string, true>, loose: array<string, true>, es: array<string, int>, mam: array<string, int>}  $cells
 * @return array{string, string, string, bool}
 */
function classify(string $line, array $cells): array
{
    // Una «celda» de castellano con apóstrofo o `ẍ` es en realidad una fila con las columnas
    // pegadas («A cada mitad, mitad para cada uno Nky’ajchaq»); una de Mam con tilde, igual.
    $inLeft = isset($cells['left'][$line]) && preg_match(MAM_LETTERS, $line) !== 1;
    $inRight = isset($cells['right'][$line]) && preg_match(SPANISH_LETTERS, $line) !== 1;

    if ($inLeft && ! $inRight) {
        return ['es', '', '', false];
    }

    // Una línea sin hueco en `-layout` puede ser castellano que sigue en otra línea
    // («recibir») o una fila con las columnas pegadas («Aceite (de cocer), grasa, manteca
    // Txam»). Si al deducir aparece Mam a la derecha, es lo segundo.
    if (isset($cells['loose'][$line]) && preg_match(MAM_LETTERS, $line) !== 1) {
        $deduced = deduceSplit(unglue($line), $cells);

        if ($deduced[0] !== 'pair') {
            $deduced = trailingCapitalSplit(unglue($line), $cells);
        }

        return $deduced[0] === 'pair' ? $deduced : ['es', '', '', false];
    }

    if ($inRight && ! $inLeft) {
        return ['mam', '', '', false];
    }

    $tokens = explode(' ', $line);

    // Del castellano más largo al más corto: «Doblar la ropa o papel» antes que «Doblar».
    for ($k = count($tokens) - 1; $k >= 1; $k--) {
        $es = implode(' ', array_slice($tokens, 0, $k));
        $mam = implode(' ', array_slice($tokens, $k));

        if (isset($cells['left'][$es], $cells['right'][$mam])) {
            return ['pair', $es, $mam, false];
        }
    }

    return deduceSplit(unglue($line), $cells);
}

/**
 * Las palabras de la línea, despegando las que el PDF unió sin espacio («rayarSqitil»,
 * «personas)Ipnejenel»): una mayúscula a mitad de palabra no existe ni en castellano ni en Mam.
 *
 * @return list<string>
 */
function unglue(string $line): array
{
    return explode(' ', (string) preg_replace('/(?<=[\p{Ll})])(?=\p{Lu})/u', ' ', $line));
}

/**
 * Último recurso para una fila pegada cuyo Mam no trae ninguna marca («… poseer Etzil»): las
 * palabras finales desde la última mayúscula, si ninguna es castellano y antes sí lo hay.
 *
 * @param  list<string>  $tokens
 * @param  array{es: array<string, int>, mam: array<string, int>}  $cells
 * @return array{string, string, string, bool}
 */
function trailingCapitalSplit(array $tokens, array $cells): array
{
    for ($k = count($tokens) - 1; $k >= 1; $k--) {
        if (preg_match('/^\p{Lu}/u', $tokens[$k]) !== 1) {
            continue;
        }

        $tail = array_map(fn (string $token) => languageScore($token, $cells), array_slice($tokens, $k));
        $head = array_map(fn (string $token) => languageScore($token, $cells), array_slice($tokens, 0, $k));

        if (! in_array(1, $tail, true) && in_array(1, $head, true)) {
            return ['pair', implode(' ', array_slice($tokens, 0, $k)), implode(' ', array_slice($tokens, $k)), true];
        }

        break;
    }

    return ['es', '', '', true];
}

/**
 * El corte que mejor deja castellano a la izquierda y Mam a la derecha.
 *
 * Cada palabra puntúa +1 si parece castellano y −1 si parece Mam, y se elige el corte que
 * maximiza (castellano antes) − (castellano después). Es deducción, así que la fila sale
 * marcada para verificar.
 *
 * @param  list<string>  $tokens
 * @param  array{es: array<string, int>, mam: array<string, int>}  $cells
 * @return array{string, string, string, bool}
 */
function deduceSplit(array $tokens, array $cells): array
{
    $scores = array_map(fn (string $token) => languageScore($token, $cells), $tokens);
    $n = count($tokens);

    $best = $n;
    $bestKey = null;

    for ($k = 0; $k <= $n; $k++) {
        $value = array_sum(array_slice($scores, 0, $k)) - array_sum(array_slice($scores, $k));

        // A igual puntuación, mejor cortar delante de una mayúscula (cada celda empieza así)
        // y, si no, lo más a la derecha posible: las palabras neutras del final («el
        // servicio») son más probablemente la cola del castellano que el comienzo del Mam.
        $startsCell = $k < $n && preg_match('/^\p{Lu}/u', $tokens[$k]) === 1;
        $key = [$value, (int) $startsCell, $k];

        if ($bestKey === null || $key > $bestKey) {
            [$best, $bestKey] = [$k, $key];
        }
    }

    // Sin una sola palabra con pinta de Mam a la derecha no hay columna de Mam, y al revés.
    if (! in_array(-1, array_slice($scores, $best), true)) {
        $best = $n;
    } elseif (! in_array(1, array_slice($scores, 0, $best), true)) {
        $best = 0;
    }

    if ($best === $n) {
        return ['es', '', '', true];
    }

    if ($best === 0) {
        return ['mam', '', '', true];
    }

    return ['pair', implode(' ', array_slice($tokens, 0, $best)), implode(' ', array_slice($tokens, $best)), true];
}

/**
 * +1 castellano, −1 Mam, 0 si no se sabe.
 *
 * Por las letras, si las hay de una sola lengua; si no, por la columna donde la palabra sale
 * al menos el doble de veces.
 *
 * @param  array{es: array<string, int>, mam: array<string, int>}  $cells
 */
function languageScore(string $token, array $cells): int
{
    $word = wordKey($token);

    if ($word === '') {
        return 0;
    }

    if (preg_match(MAM_LETTERS, $word) === 1) {
        return -1;
    }

    if (preg_match(SPANISH_LETTERS, $word) === 1) {
        return 1;
    }

    $es = $cells['es'][$word] ?? 0;
    $mam = $cells['mam'][$word] ?? 0;

    return match (true) {
        $es > 2 * $mam => 1,
        $mam > 2 * $es => -1,
        default => 0,
    };
}

function wordKey(string $token): string
{
    return trim(mb_strtolower($token), " \t.,;:()\"¿?¡!");
}

/**
 * Lema del COLIMAM (normalizado y en minúsculas) => raíces de las palabras de su glosa.
 *
 * @return array<string, array<string, true>>
 */
function colimamGlosses(string $text): array
{
    $glosses = [];

    foreach (preg_split('/\R/u', $text) as $line) {
        if (! preg_match('/^([^|]{1,60}?) \|\|(.*)$/u', $line, $match)) {
            continue;
        }

        $lemma = mb_strtolower(TextNormalizer::normalize(trim($match[1])));
        $glosses[$lemma] = ($glosses[$lemma] ?? []) + stems(mb_substr($match[2], 0, 240));
    }

    return $glosses;
}

/**
 * `coincide` si el COLIMAM tiene el lema y su glosa comparte alguna palabra con el castellano;
 * `difiere` si lo tiene con otra glosa; vacío si no lo tiene.
 *
 * @param  array<string, array<string, true>>  $colimam
 */
function colimamCheck(array $colimam, string $mam, string $es): string
{
    $lemma = mb_strtolower($mam);

    if ($lemma === '' || ! isset($colimam[$lemma])) {
        return '';
    }

    return array_intersect_key($colimam[$lemma], stems($es)) === [] ? 'difiere' : 'coincide';
}

/**
 * Raíces burdas de las palabras con contenido: sin tildes, sin las vacías y cortadas a cinco
 * letras, para que «silla» y «sillas» cuenten como la misma.
 *
 * @return array<string, true>
 */
function stems(string $text): array
{
    $plain = strtr(mb_strtolower($text), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    preg_match_all('/[a-zñ]{3,}/u', $plain, $matches);

    $stems = [];

    foreach ($matches[0] as $word) {
        if (! in_array($word, STOPWORDS, true)) {
            $stems[mb_substr($word, 0, 5)] = true;
        }
    }

    return $stems;
}
