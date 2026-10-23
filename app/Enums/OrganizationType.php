<?php

namespace App\Enums;

/**
 * Legal type of the school owner (sostenedor).
 */
enum OrganizationType: string
{
    case Slep = 'slep';
    case Dem = 'dem';
    case Daem = 'daem';
    case Corporacion = 'corporacion';
    case Fundacion = 'fundacion';

    /**
     * Human readable label (Spanish).
     */
    public function label(): string
    {
        return match ($this) {
            self::Slep => 'SLEP',
            self::Dem => 'DEM',
            self::Daem => 'DAEM',
            self::Corporacion => 'Corporación',
            self::Fundacion => 'Fundación',
        };
    }
}
