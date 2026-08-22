<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\CategoriaGramatical;
use App\Models\Entrada;
use App\Models\Fuente;
use App\Models\VersionContenido;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Vocabulario de demostración: 42 palabras reales en seis temas, listas para exportar.
 *
 * Existe para que el frontend tenga contra qué maquetar. Sin datos no se puede construir
 * ni un buscador ni una navegación alfabética, y el diccionario no se llena solo: lo carga
 * el docente desde el panel, o lo llenará el importador del COLIMAM en la Fase 4.
 *
 * ## De dónde salen estas palabras
 *
 * **Todas tienen fuente y ninguna está inventada.** Treinta y seis se extrajeron del
 * Diccionario Mam de COLIMAM (ALMG); las seis que llevan `ẍ` se citan del Manual de Normas
 * Ortográficas del proyecto, porque el COLIMAM las imprime con la `ẍ` corrompida en `õ` y
 * de ahí no se pueden citar sin ambigüedad.
 *
 * Importa decirlo porque es fácil equivocarse: una versión anterior de `EntradaFactory`
 * traía glosas escritas de memoria y tres de catorce estaban mal —`chmol` figuraba como
 * «reunión» cuando significa **araña**—. En un proyecto cuyo objeto es preservar un idioma,
 * un dato inventado es una deuda que alguien va a pagar.
 *
 * Lo único que sí es decisión de este archivo es **el reparto por temas**: agrupar «perro»
 * en «animales» no es una afirmación lingüística, es la misma decisión editorial que
 * tomaría el docente en el panel.
 *
 * ## Advertencias
 *
 * - Los lemas se citan en minúsculas; el diccionario los imprime en versalitas.
 * - Las entradas quedan **marcadas como revisadas** para que la exportación no salga vacía.
 *   Eso es una conveniencia de desarrollo: ninguna pasó por el validador lingüístico, y
 *   `revisado_por` queda nulo justamente para que se note.
 * - Los temas no llevan `nombre_mam`: no tengo fuente para traducirlos y no los voy a
 *   inventar. Es también un buen recordatorio de que la columna es anulable.
 *
 * Se carga y se descarga con `php artisan mam:demo` y `php artisan mam:demo --limpiar`.
 */
class VocabularioDemoSeeder extends Seeder
{
    /**
     * Sin eventos: cuarenta y dos altas dispararían cuarenta y dos incrementos de versión y
     * otros tantos trabajos de publicación encolados. La versión se sube una vez al final.
     */
    use WithoutModelEvents;

    private const TEMAS = [
        'animales' => ['Animales', 1],
        'la-familia' => ['La familia', 2],
        'colores' => ['Colores', 3],
        'numeros' => ['Números', 4],
        'la-naturaleza' => ['La naturaleza', 5],
        'la-casa' => ['La casa', 6],
        'alimentos' => ['Alimentos', 7],
    ];

    private const FUENTES = [
        'colimam' => ['Diccionario Mam', 'Comunidad Lingüística Mam (COLIMAM), ALMG', 2011],
        'manual' => ['Manual de Normas Ortográficas y Carga de Contenido', 'AprendeMam', 2026],
    ];

    /** @var list<array{string, string, string, string, string}> tema, mam, español, clase, fuente */
    private const VOCABULARIO = [
        ['animales', 'tx’yan', 'perro', 's.', 'colimam'],
        ['animales', 'ch’it', 'pájaro', 's.', 'colimam'],
        ['animales', 'ja’w', 'tacuazín', 's.', 'colimam'],
        ['animales', 'chmol', 'araña', 's.', 'colimam'],
        ['animales', 'ch’el', 'chocoyo o perica', 's.', 'colimam'],
        ['la-familia', 'anab’', 'hermana de hombre', 's.', 'colimam'],
        ['la-familia', 'k’ajol', 'hijo de hombre', 's.', 'colimam'],
        ['la-familia', 'me’al', 'hija (de hombre)', 's.', 'colimam'],
        ['la-familia', 'xib’en', 'hermano mayor de mujer', 's.', 'colimam'],
        ['la-familia', 'ya’', 'abuela', 's.', 'colimam'],
        ['la-familia', 'we’j', 'abuelo', 's.', 'colimam'],
        ['colores', 'cha’x', 'azul', 'adj.', 'colimam'],
        ['colores', 'kyeq', 'rojo', 'adj.', 'colimam'],
        ['colores', 'q’an', 'amarillo', 'adj.', 'colimam'],
        ['colores', 'q’eq', 'negro', 'adj.', 'colimam'],
        ['colores', 'saq', 'blanco', 'adj.', 'colimam'],
        ['numeros', 'jwe’', 'cinco', 'num.', 'colimam'],
        ['numeros', 'wuq', 'siete', 'num.', 'colimam'],
        ['numeros', 'wajxaq', 'ocho', 'num.', 'colimam'],
        ['numeros', 'b’elaj', 'nueve', 'num.', 'colimam'],
        ['la-naturaleza', 'tx’otx’', 'tierra', 's.', 'colimam'],
        ['la-naturaleza', 'xaq', 'piedra', 's.', 'colimam'],
        ['la-naturaleza', 'q’aq’', 'fuego', 's.', 'colimam'],
        ['la-naturaleza', 'tzaj', 'pino', 's.', 'colimam'],
        ['la-naturaleza', 'a’witz', 'hoja', 's.', 'colimam'],
        ['la-naturaleza', 'tub’chil', 'flor', 's.', 'colimam'],
        ['la-casa', 'watb’il', 'cama', 's.', 'colimam'],
        ['la-casa', 'tutz’bil', 'silla', 's.', 'colimam'],
        ['la-casa', 'tlamel ja', 'puerta', 's.', 'colimam'],
        ['la-casa', 'chjkan', 'olla', 's.', 'colimam'],
        ['la-casa', 'mejb’il', 'petate', 's.', 'colimam'],
        ['la-casa', 'wab’il', 'mesa', 's.', 'colimam'],
        ['la-casa', 'tz’is', 'basura', 's.', 'colimam'],
        ['alimentos', 'jal', 'mazorca', 's.', 'colimam'],
        ['alimentos', 'k’uxub’aj', 'elote', 's.', 'colimam'],
        ['alimentos', 'txan', 'güisquil', 's.', 'colimam'],
        ['animales', 'ẍiky', 'conejo', 's.', 'manual'],
        ['animales', 'kyiẍ', 'pez; pescado', 's.', 'manual'],
        ['animales', 'wiẍ', 'gato', 's.', 'manual'],
        ['alimentos', 'i’ẍ', 'elote', 's.', 'manual'],
        ['alimentos', 'napẍ', 'nabo', 's.', 'manual'],
        ['la-casa', 'ẍoq’', 'tinaja', 's.', 'manual'],
    ];

    public function run(): void
    {
        $fuentes = [];

        foreach (self::FUENTES as $clave => [$titulo, $institucion, $anio]) {
            $fuentes[$clave] = Fuente::firstOrCreate(
                ['titulo' => $titulo],
                ['institucion' => $institucion, 'anio' => $anio]
            );
        }

        $temas = [];

        foreach (self::TEMAS as $slug => [$nombre, $orden]) {
            $temas[$slug] = Categoria::firstOrCreate(
                ['slug' => $slug],
                ['nombre_es' => $nombre, 'orden' => $orden]
            );
        }

        $clases = CategoriaGramatical::query()->pluck('id', 'abreviatura');

        foreach (self::VOCABULARIO as [$tema, $mam, $espanol, $clase, $fuente]) {
            // Idempotente: volver a sembrar no duplica ni pisa nada.
            $entrada = Entrada::firstOrCreate(
                ['mam' => $mam, 'espanol' => $espanol],
                [
                    'categoria_gramatical_id' => $clases[$clase] ?? null,
                    'fuente_id' => $fuentes[$fuente]->id,
                ]
            );

            // `revisado` no es asignable en masa a propósito: solo el validador lo mueve.
            // Acá se fuerza para que la exportación tenga algo que entregar.
            if (! $entrada->revisado) {
                $entrada->revisado = true;
                $entrada->save();
            }

            $entrada->categorias()->syncWithoutDetaching([$temas[$tema]->id]);
        }

        VersionContenido::incrementar();
    }

    /**
     * Borra lo que sembró, sin llevarse por delante trabajo de nadie.
     *
     * El criterio es el lema **más la ausencia de autoría**. Una entrada cargada por una
     * persona desde el panel siempre lleva `creado_por`; las que siembra este archivo no,
     * porque se crean sin sesión abierta. Así, si el docente cargó su propia versión de
     * «jal», la suya sobrevive.
     *
     * No se identifica por la traducción —cambiar una glosa dejaría huérfana la fila
     * anterior, imposible de encontrar después— ni por la fuente, porque una entrada real
     * puede citar legítimamente el mismo Diccionario COLIMAM.
     */
    public static function limpiar(): int
    {
        $borradas = Entrada::query()
            ->whereIn('mam', array_column(self::VOCABULARIO, 1))
            ->whereNull('creado_por')
            ->delete();

        Categoria::query()->whereIn('slug', array_keys(self::TEMAS))
            ->whereDoesntHave('entradas')
            ->delete();

        Fuente::query()->whereIn('titulo', array_column(self::FUENTES, 0))
            ->whereDoesntHave('entradas')
            ->delete();

        return $borradas;
    }
}
