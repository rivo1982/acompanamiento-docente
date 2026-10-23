<?php

namespace App\Enums;

/**
 * Formative achievement level assigned to an indicator during an observation.
 */
enum AchievementLevel: string
{
    case Beginning = 'en_inicio';
    case Developing = 'en_desarrollo';
    case Achieved = 'logrado';
    case Outstanding = 'destacado';

    /**
     * Human readable label (Spanish).
     */
    public function label(): string
    {
        return match ($this) {
            self::Beginning => 'En inicio',
            self::Developing => 'En desarrollo',
            self::Achieved => 'Logrado',
            self::Outstanding => 'Destacado',
        };
    }

    /**
     * General meaning of the level, shown on every rubric.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::Beginning => 'La práctica aparece de forma aislada o con efecto limitado en los estudiantes. El descriptor no aplica a la clase y no es posible observar.',
            self::Developing => 'Aparece en parte de la clase o con parte del grupo, con vacíos que limitan su efecto.',
            self::Achieved => 'Se da de manera consistente y los estudiantes responden como se espera. Felicitaciones.',
            self::Outstanding => 'Además de lo logrado, la práctica se adapta a la diversidad del grupo y los estudiantes la ejercen con autonomía. Felicitaciones por su labor y su exitoso desarrollo profesional.',
        };
    }

    /**
     * Congratulation appended to the automatic feedback, if the level earns one.
     */
    public function congratulationMessage(): ?string
    {
        return match ($this) {
            self::Achieved => 'Felicitaciones',
            self::Outstanding => 'Felicitaciones por su labor y su exitoso desarrollo profesional.',
            default => null,
        };
    }

    /**
     * Level meaning table in ascending order.
     *
     * @return array<int, array{level: string, label: string, meaning: string}>
     */
    public static function meaningTable(): array
    {
        return array_map(
            fn (self $level): array => [
                'level' => $level->value,
                'label' => $level->label(),
                'meaning' => $level->meaning(),
            ],
            self::cases(),
        );
    }
}
