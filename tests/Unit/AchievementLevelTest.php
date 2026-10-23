<?php

namespace Tests\Unit;

use App\Enums\AchievementLevel;
use PHPUnit\Framework\TestCase;

class AchievementLevelTest extends TestCase
{
    public function test_levels_have_spanish_labels(): void
    {
        $this->assertSame('En inicio', AchievementLevel::Beginning->label());
        $this->assertSame('En desarrollo', AchievementLevel::Developing->label());
        $this->assertSame('Logrado', AchievementLevel::Achieved->label());
        $this->assertSame('Destacado', AchievementLevel::Outstanding->label());
    }

    public function test_levels_have_the_official_meaning_texts(): void
    {
        $this->assertSame(
            'La práctica aparece de forma aislada o con efecto limitado en los estudiantes. El descriptor no aplica a la clase y no es posible observar.',
            AchievementLevel::Beginning->meaning(),
        );
        $this->assertSame(
            'Aparece en parte de la clase o con parte del grupo, con vacíos que limitan su efecto.',
            AchievementLevel::Developing->meaning(),
        );
        $this->assertSame(
            'Se da de manera consistente y los estudiantes responden como se espera. Felicitaciones.',
            AchievementLevel::Achieved->meaning(),
        );
        $this->assertSame(
            'Además de lo logrado, la práctica se adapta a la diversidad del grupo y los estudiantes la ejercen con autonomía. Felicitaciones por su labor y su exitoso desarrollo profesional.',
            AchievementLevel::Outstanding->meaning(),
        );
    }

    public function test_only_achieved_and_outstanding_levels_congratulate(): void
    {
        $this->assertNull(AchievementLevel::Beginning->congratulationMessage());
        $this->assertNull(AchievementLevel::Developing->congratulationMessage());
        $this->assertSame('Felicitaciones', AchievementLevel::Achieved->congratulationMessage());
        $this->assertSame(
            'Felicitaciones por su labor y su exitoso desarrollo profesional.',
            AchievementLevel::Outstanding->congratulationMessage(),
        );
    }

    public function test_meaning_table_lists_the_four_levels_in_ascending_order(): void
    {
        $table = AchievementLevel::meaningTable();

        $this->assertSame(
            ['en_inicio', 'en_desarrollo', 'logrado', 'destacado'],
            array_column($table, 'level'),
        );
        $this->assertSame(
            ['level' => 'logrado', 'label' => 'Logrado', 'meaning' => AchievementLevel::Achieved->meaning()],
            $table[2],
        );
    }
}
