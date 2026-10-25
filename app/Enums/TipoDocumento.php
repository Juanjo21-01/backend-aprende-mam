<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué clase de material descargable es un documento.
 *
 * Lista cerrada y no texto libre: el sitio agrupa y filtra los materiales por tipo, y «guía»
 * escrito de tres maneras serían tres filtros.
 */
enum TipoDocumento: string
{
    case Guia = 'guia';

    case Ficha = 'ficha';

    case Cuadernillo = 'cuadernillo';

    case Infografia = 'infografia';

    public function label(): string
    {
        return match ($this) {
            self::Guia => 'Guía',
            self::Ficha => 'Ficha',
            self::Cuadernillo => 'Cuadernillo',
            self::Infografia => 'Infografía',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
