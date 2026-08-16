<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Los dos únicos roles del panel.
 *
 * No hay tabla de roles ni de permisos: son dos y no van a crecer. La diferencia entre ambos
 * la resuelven las políticas, no una columna de banderas.
 *
 * El editor carga contenido; el administrador además borra y marca lo revisado. Esa segunda
 * atribución es la del **validador lingüístico** del que hablan la Propuesta Funcional y el
 * Manual de Normas: la persona que aprueba el material en Mam antes de publicarlo.
 *
 * Los valores van en castellano porque viven en una columna de la base.
 */
enum UserRole: string
{
    case Administrator = 'administrador';

    case Editor = 'editor';

    /** Para mostrarlo en el panel, que está en castellano. */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Editor => 'Editor',
        };
    }
}
