<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Observers\PublishableContentObserver;
use App\Support\Mam\TextNormalizer;
use App\Support\Mam\Tokenizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa vocabulario desde el CSV intermedio que se extrae de un PDF fuente.
 *
 * El CSV es la evidencia trazable de la importación: una fila por variante, tal como sale de
 * la fuente, con su página y el tema que se le asignó. Este comando no lee PDF; solo convierte
 * ese CSV, ya revisado, en entradas.
 *
 * ## Columnas
 *
 * `mam`, `espanol`, `pagina`, `tema` (slugs separados por `|`) y `estado`, que es uno de:
 *
 * - `ok`: se importa si tiene tema; sin tema se queda fuera, porque no encajó en ninguno.
 * - `frase`: una oración y no una palabra. Se queda fuera.
 * - `revisar`: la extracción la dejó mal (columnas mezcladas, filas partidas). Se queda
 *   fuera hasta que alguien la corrija a mano y la pase a `ok`.
 *
 * `tema_origen` y `notas` son para quien revisa el CSV; el comando no las lee.
 *
 * ## Garantías
 *
 * - **Todo o nada.** Se valida el archivo entero antes de escribir. Con un solo error no se
 *   importa ninguna fila, y se listan todos los errores con su línea.
 * - **Ortografía.** El lema pasa por el normalizador y a minúsculas, y se rechaza si tiene
 *   algo que no es grafema del Mam (como la `b` sin saltillo de `tutz’bil`).
 * - **Sin duplicados.** La misma forma en varias filas es una entrada: se juntan castellano,
 *   páginas y temas. Una forma que ya está en la base no se toca; se reporta.
 * - **Nace sin revisar.** Y por eso no sube la versión ni programa publicación: no cambia la
 *   exportación. Lo publica la firma del validador.
 */
class ImportVocabulary extends Command
{
    protected $signature = 'mam:importar-vocabulario
                            {archivo : Ruta del CSV}
                            {--fuente= : id de la fuente bibliográfica de todas las filas}
                            {--simular : Valida y reporta sin guardar nada}';

    protected $description = 'Importa vocabulario desde el CSV intermedio de una fuente';

    private const REQUIRED_COLUMNS = ['mam', 'espanol', 'pagina', 'tema', 'estado'];

    private const STATES = ['ok', 'frase', 'revisar'];

    /** Lo que miden las columnas en la migración de `entradas`. */
    private const MAM_MAX_LENGTH = 191;

    private const PAGES_MAX_LENGTH = 20;

    /** @var list<string> */
    private array $errors = [];

    public function handle(Tokenizer $tokenizer): int
    {
        $fuente = Fuente::query()->find($this->option('fuente'));

        if ($fuente === null) {
            $this->error('Falta --fuente o no existe una fuente con ese id. Crearla antes en el panel.');

            return self::FAILURE;
        }

        $rows = $this->readCsv((string) $this->argument('archivo'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $temas = Categoria::query()->pluck('id', 'slug');

        $counts = ['frase' => 0, 'revisar' => 0, 'sin_tema' => 0, 'ok' => 0];

        /** @var array<string, array{line: int, mam: string, espanol: list<string>, pages: list<int>, temas: list<string>}> $groups */
        $groups = [];

        foreach ($rows as $line => $row) {
            $state = mb_strtolower(trim($row['estado']));

            if (! in_array($state, self::STATES, true)) {
                $this->reject($line, sprintf('el estado «%s» no existe; debe ser ok, frase o revisar.', $row['estado']));

                continue;
            }

            if ($state !== 'ok') {
                $counts[$state]++;

                continue;
            }

            $slugs = array_values(array_unique(array_filter(array_map('trim', explode('|', $row['tema'])))));

            if ($slugs === []) {
                $counts['sin_tema']++;

                continue;
            }

            $mam = $this->lemma($row['mam']);
            $espanol = trim($row['espanol']);
            $page = trim($row['pagina']);

            if ($mam === '') {
                $this->reject($line, 'falta la forma en Mam (columna mam).');

                continue;
            }

            $invalid = $tokenizer->invalidLemmaCharacters($mam);

            if ($invalid !== []) {
                $this->reject($line, sprintf(
                    '«%s» tiene caracteres fuera del alfabeto Mam: %s.',
                    $mam,
                    implode(', ', array_map(fn (string $char) => '«'.$char.'»', $invalid)),
                ));
            }

            if (mb_strlen($mam) > self::MAM_MAX_LENGTH) {
                $this->reject($line, sprintf('la forma en Mam pasa de %d caracteres.', self::MAM_MAX_LENGTH));
            }

            if ($espanol === '') {
                $this->reject($line, 'falta el castellano (columna espanol).');
            }

            if (! ctype_digit($page) || (int) $page === 0) {
                $this->reject($line, sprintf('la página «%s» no es un número de página.', $page));
            }

            foreach ($slugs as $slug) {
                if (! $temas->has($slug)) {
                    $this->reject($line, sprintf('el tema «%s» no existe. Sembrar los temas o corregir el slug.', $slug));
                }
            }

            $counts['ok']++;

            // La clave es el lema ya normalizado y en minúsculas, comparado en PHP: la fuente
            // capitaliza la primera variante de cada fila y no las demás.
            $group = $groups[$mam] ?? ['line' => $line, 'mam' => $mam, 'espanol' => [], 'pages' => [], 'temas' => []];

            if ($espanol !== '' && ! in_array($espanol, $group['espanol'], true)) {
                $group['espanol'][] = $espanol;
            }

            if (ctype_digit($page) && ! in_array((int) $page, $group['pages'], true)) {
                $group['pages'][] = (int) $page;
            }

            $group['temas'] = array_values(array_unique([...$group['temas'], ...$slugs]));
            $groups[$mam] = $group;
        }

        foreach ($groups as $group) {
            sort($group['pages']);

            if (strlen(implode(', ', $group['pages'])) > self::PAGES_MAX_LENGTH) {
                $this->reject($group['line'], sprintf(
                    '«%s» aparece en demasiadas páginas (%s) para los %d caracteres de pagina_fuente. '.
                    'Quitar filas repetidas del CSV.',
                    $group['mam'],
                    implode(', ', $group['pages']),
                    self::PAGES_MAX_LENGTH,
                ));
            }
        }

        if ($this->errors !== []) {
            foreach ($this->errors as $error) {
                $this->error($error);
            }

            $this->newLine();
            $this->error(sprintf('%d errores. No se importó nada: corregir el CSV y volver a correr.', count($this->errors)));

            return self::FAILURE;
        }

        // Contra la base en PHP y no con `WHERE mam IN (...)`: para `utf8mb4_unicode_ci`, `x` y
        // `ẍ` son la misma letra, y se daría por existente una palabra que no lo es.
        $existing = Entrada::query()->toBase()->pluck('mam')
            ->mapWithKeys(fn (string $mam) => [mb_strtolower($mam) => true]);

        $new = array_filter($groups, fn (array $group) => ! $existing->has($group['mam']));

        $this->line('Filas listas: '.$counts['ok']);
        $this->line('Frases: '.$counts['frase']);
        $this->line('Por revisar: '.$counts['revisar']);
        $this->line('Sin tema: '.$counts['sin_tema']);
        $this->line('Fusionadas en otra fila: '.($counts['ok'] - count($groups)));
        $this->line('Ya existentes: '.(count($groups) - count($new)));
        $this->info('Entradas nuevas: '.count($new));

        if ($this->option('simular')) {
            $this->newLine();
            $this->warn('Simulación: no se guardó nada.');

            return self::SUCCESS;
        }

        PublishableContentObserver::withoutPublishing(fn () => DB::transaction(function () use ($new, $fuente, $temas): void {
            foreach ($new as $group) {
                sort($group['pages']);

                $entrada = Entrada::query()->create([
                    'mam' => $group['mam'],
                    'espanol' => implode('; ', $group['espanol']),
                    'fuente_id' => $fuente->id,
                    'pagina_fuente' => implode(', ', $group['pages']),
                ]);

                $entrada->categorias()->attach(
                    array_map(fn (string $slug) => $temas[$slug], $group['temas'])
                );
            }
        }));

        $this->newLine();
        $this->info('Importación terminada. Todo quedó sin revisar: lo publica la firma del validador.');

        return self::SUCCESS;
    }

    /**
     * Las filas del CSV indexadas por su número de línea (la 1 es el encabezado), o `null` si el
     * archivo no se puede leer. El número de línea asume que ningún campo trae saltos de línea,
     * que es como lo escribe el script de extracción.
     *
     * @return array<int, array<string, string>>|null
     */
    private function readCsv(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("No se encuentra el archivo {$path}.");

            return null;
        }

        $content = (string) file_get_contents($path);

        // Lo que deja Excel al guardar como CSV UTF-8. Sin quitarlo, la primera columna se
        // llamaría «\u{FEFF}mam» y faltaría `mam`.
        if (str_starts_with($content, "\u{FEFF}")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $this->error('El archivo no está en UTF-8. Guardarlo como «CSV UTF-8».');

            return null;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = array_map('trim', fgetcsv($stream, escape: '') ?: []);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            fclose($stream);
            $this->error('Faltan columnas en el encabezado: '.implode(', ', $missing).'.');

            return null;
        }

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($stream, escape: '')) !== false) {
            $line++;

            // `fgetcsv` devuelve `[null]` en una línea en blanco.
            if ($values === [null]) {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $row[$column] = (string) ($values[$index] ?? '');
            }

            $rows[$line] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * El lema como se guarda: normalizado y en minúsculas, con los espacios sobrantes fuera.
     *
     * Las minúsculas van después de normalizar para que `Õ` llegue como `ẍ` y no como `õ`.
     * El mutator vuelve a normalizar, y es idempotente.
     */
    private function lemma(string $raw): string
    {
        $lemma = mb_strtolower(TextNormalizer::normalize(trim($raw)), 'UTF-8');

        return (string) preg_replace('/ {2,}/', ' ', $lemma);
    }

    private function reject(int $line, string $message): void
    {
        $this->errors[] = "Línea {$line}: {$message}";
    }
}
