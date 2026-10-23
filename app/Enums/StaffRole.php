<?php

namespace App\Enums;

/**
 * Role of a staff member within a school or organization.
 */
enum StaffRole: string
{
    case Teacher = 'docente';
    case Principal = 'directivo';
    case CurriculumHead = 'utp';
    case OrganizationAdmin = 'sostenedor';

    /**
     * Human readable label (Spanish).
     */
    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Docente',
            self::Principal => 'Directivo',
            self::CurriculumHead => 'Jefe/a UTP',
            self::OrganizationAdmin => 'Sostenedor',
        };
    }

    /**
     * School leadership (Dirección/UTP) administers the school and always observes classes.
     */
    public function isSchoolAdmin(): bool
    {
        return in_array($this, [self::Principal, self::CurriculumHead], true);
    }
}
