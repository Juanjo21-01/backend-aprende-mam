<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprueba que la conexión MySQL usa realmente utf8mb4 / utf8mb4_unicode_ci.
 *
 * No basta con leerlo de config/database.php: lo que importa es lo que negocia
 * el servidor al abrir la conexión y lo que sobrevive a un viaje de ida y vuelta.
 * Un charset mal negociado no lanza ningún error; simplemente reemplaza `ẍ` por
 * `?` y nadie se entera hasta que el diccionario ya está cargado.
 */
class VerifyCharset extends Command
{
    protected $signature = 'mam:verificar-charset
                            {--connection= : Conexión a verificar; por defecto la predeterminada}';

    protected $description = 'Verifica que la conexión use utf8mb4/utf8mb4_unicode_ci y que el texto en Mam viaje intacto';

    private const CHARSET = 'utf8mb4';

    private const COLLATION = 'utf8mb4_unicode_ci';

    /**
     * Muestras con los grafemas que se rompen primero: `ẍ` (U+1E8D), fuera de
     * Latin-1, y el apóstrofo canónico U+2019, que no es el U+0027 del teclado.
     */
    private const SAMPLES = [
        "tx'otx'",  // U+2019 duplicado
        'ẍiky',     // U+1E8D inicial
        'kyiẍ',     // U+1E8D final
        "xq'exwi'", // apóstrofo glotalizante y saltillo en la misma palabra
        '’',        // saltillo aislado, U+2019
    ];

    private bool $failed = false;

    public function handle(): int
    {
        $name = $this->option('connection') ?: config('database.default');
        $connection = DB::connection($name);

        $this->line('');
        $this->line("Conexión: <options=bold>{$name}</> (driver: {$connection->getDriverName()})");

        if ($connection->getDriverName() !== 'mysql') {
            $this->error("Esta verificación solo aplica a MySQL; «{$name}» usa «{$connection->getDriverName()}».");

            return self::FAILURE;
        }

        try {
            $this->checkSessionVariables($connection);
            $this->checkDatabaseDefaults($connection);
            $this->checkRoundTrip($connection);
            $this->checkExistingColumns($connection);
            $this->reportCollationSensitivity($connection);
        } catch (Throwable $e) {
            $this->line('');
            $this->error('No se pudo completar la verificación: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('');

        if ($this->failed) {
            $this->error('La conexión NO cumple utf8mb4/utf8mb4_unicode_ci. No cargues datos en Mam hasta corregirlo.');

            return self::FAILURE;
        }

        $this->info('La conexión usa utf8mb4/utf8mb4_unicode_ci y el texto en Mam sobrevive el viaje de ida y vuelta.');

        return self::SUCCESS;
    }

    /**
     * Lo que el servidor tiene realmente negociado para esta sesión.
     * `character_set_server` es informativo: si la conexión y las tablas son
     * utf8mb4, un servidor en latin1 no corrompe nada.
     */
    private function checkSessionVariables(mixed $connection): void
    {
        $required = [
            'character_set_client' => self::CHARSET,
            'character_set_connection' => self::CHARSET,
            'character_set_results' => self::CHARSET,
            'collation_connection' => self::COLLATION,
        ];

        $informative = ['character_set_server', 'collation_server'];

        $vars = $this->variables($connection, array_merge(array_keys($required), $informative));

        $rows = [];

        foreach ($required as $variable => $expected) {
            $actual = $vars[$variable] ?? '(ausente)';
            $ok = $actual === $expected;
            $this->failed = $this->failed || ! $ok;
            $rows[] = [$variable, $actual, $expected, $ok ? 'OK' : 'FALLA'];
        }

        foreach ($informative as $variable) {
            $rows[] = [$variable, $vars[$variable] ?? '(ausente)', '—', 'informativo'];
        }

        $this->line('');
        $this->line('<options=bold>Variables de sesión</>');
        $this->table(['Variable', 'Actual', 'Esperado', 'Estado'], $rows);
    }

    /**
     * Los valores por defecto del esquema. Determinan qué heredan las tablas
     * que se creen sin declarar charset, así que una base mal creada es una
     * bomba de tiempo aunque la sesión esté bien.
     */
    private function checkDatabaseDefaults(mixed $connection): void
    {
        $schema = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'select default_character_set_name as charset, default_collation_name as collation
               from information_schema.schemata
              where schema_name = ?',
            [$schema]
        );

        if ($row === null) {
            $this->failed = true;
            $this->line('');
            $this->error("El esquema «{$schema}» no existe.");

            return;
        }

        $charsetOk = $row->charset === self::CHARSET;
        $collationOk = $row->collation === self::COLLATION;
        $this->failed = $this->failed || ! $charsetOk || ! $collationOk;

        $this->line('');
        $this->line("<options=bold>Valores por defecto del esquema «{$schema}»</>");
        $this->table(['Propiedad', 'Actual', 'Esperado', 'Estado'], [
            ['charset', $row->charset, self::CHARSET, $charsetOk ? 'OK' : 'FALLA'],
            ['collation', $row->collation, self::COLLATION, $collationOk ? 'OK' : 'FALLA'],
        ]);

        if (! $charsetOk || ! $collationOk) {
            $this->warn("  Corregir con: ALTER DATABASE `{$schema}` CHARACTER SET ".self::CHARSET.' COLLATE '.self::COLLATION.';');
        }
    }

    /**
     * La prueba que de verdad importa: escribir texto en Mam en una tabla con el
     * charset del proyecto y recuperarlo byte por byte idéntico.
     */
    private function checkRoundTrip(mixed $connection): void
    {
        $connection->statement('drop temporary table if exists _mam_sonda_charset');
        $connection->statement(
            'create temporary table _mam_sonda_charset (valor varchar(32) not null)
             engine=InnoDB default charset='.self::CHARSET.' collate '.self::COLLATION
        );

        $rows = [];

        foreach (self::SAMPLES as $sample) {
            $connection->table('_mam_sonda_charset')->insert(['valor' => $sample]);

            $stored = $connection->table('_mam_sonda_charset')->value('valor');
            $connection->table('_mam_sonda_charset')->delete();

            $ok = $stored === $sample && mb_check_encoding((string) $stored, 'UTF-8');
            $this->failed = $this->failed || ! $ok;

            $rows[] = [
                $sample,
                (string) $stored,
                $this->codepoints($sample),
                $ok ? 'OK' : 'CORRUPTO → '.$this->codepoints((string) $stored),
            ];
        }

        $connection->statement('drop temporary table if exists _mam_sonda_charset');

        $this->line('');
        $this->line('<options=bold>Ida y vuelta de texto en Mam</>');
        $this->table(['Enviado', 'Recuperado', 'Puntos de código', 'Estado'], $rows);
    }

    /**
     * Auditoría de lo ya creado. La regla del proyecto no admite excepciones:
     * toda columna de texto va en utf8mb4_unicode_ci.
     */
    private function checkExistingColumns(mixed $connection): void
    {
        $offenders = $connection->select(
            'select table_name as tabla, column_name as columna, collation_name as collation
               from information_schema.columns
              where table_schema = ?
                and collation_name is not null
                and collation_name <> ?
           order by table_name, ordinal_position',
            [$connection->getDatabaseName(), self::COLLATION]
        );

        $this->line('');
        $this->line('<options=bold>Columnas de texto fuera de '.self::COLLATION.'</>');

        if ($offenders === []) {
            $this->line('  Ninguna.');

            return;
        }

        $this->failed = true;
        $this->table(
            ['Tabla', 'Columna', 'Collation'],
            array_map(fn ($r) => [$r->tabla, $r->columna, $r->collation], $offenders)
        );
    }

    /**
     * utf8mb4_unicode_ci ignora los acentos: para MySQL, `x` y `ẍ` son la misma
     * cadena. No es un fallo de configuración, es cómo funciona el cotejamiento,
     * pero condiciona el diseño del esquema y conviene tenerlo a la vista.
     */
    private function reportCollationSensitivity(mixed $connection): void
    {
        $pairs = [
            ['x', 'ẍ', 'grafemas distintos (posiciones 29 y 30)'],
            ['’', "'", 'apóstrofo canónico U+2019 vs. U+0027'],
        ];

        $rows = [];

        foreach ($pairs as [$a, $b, $note]) {
            $equal = (bool) $connection->selectOne(
                'select (convert(? using '.self::CHARSET.') collate '.self::COLLATION.') = '.
                '(convert(? using '.self::CHARSET.') collate '.self::COLLATION.') as iguales',
                [$a, $b]
            )->iguales;

            $rows[] = [$a.'  ==  '.$b, $equal ? 'SÍ (indistinguibles)' : 'no', $note];
        }

        $this->line('');
        $this->line('<options=bold>Comportamiento del cotejamiento (informativo)</>');
        $this->table(['Comparación', '¿Iguales para MySQL?', 'Nota'], $rows);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private function variables(mixed $connection, array $names): array
    {
        $placeholders = implode(', ', array_fill(0, count($names), '?'));

        $rows = $connection->select(
            "show variables where Variable_name in ({$placeholders})",
            $names
        );

        $out = [];

        foreach ($rows as $row) {
            $out[$row->Variable_name] = $row->Value;
        }

        return $out;
    }

    /** Los puntos de código son la única forma de ver la corrupción: en pantalla `ẍ` y `x` se parecen. */
    private function codepoints(string $value): string
    {
        $parts = [];

        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $parts[] = sprintf('U+%04X', mb_ord($char, 'UTF-8'));
        }

        return implode(' ', $parts);
    }
}
