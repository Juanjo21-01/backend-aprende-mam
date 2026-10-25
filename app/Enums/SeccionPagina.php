<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Las secciones editoriales del sitio que no son vocabulario.
 *
 * Son tres y el sitio público tiene una página de índice por cada una; el valor es también la
 * clave bajo la que sale `contenido.json`, así que cambiarlo es cambiar el contrato con el
 * frontend. Van sin tilde y en minúsculas por la misma razón que un slug: viajan en una URL.
 */
enum SeccionPagina: string
{
    case Cultura = 'cultura';

    case Gramatica = 'gramatica';

    case BaseLegal = 'base_legal';

    /** Para mostrarla en el panel, que está en castellano. */
    public function label(): string
    {
        return match ($this) {
            self::Cultura => 'Cultura',
            self::Gramatica => 'Gramática',
            self::BaseLegal => 'Base legal',
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
