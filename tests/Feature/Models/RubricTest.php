<?php

namespace Tests\Feature\Models;

use App\Enums\AchievementLevel;
use App\Models\Indicator;
use App\Models\Rubric;
use App\Models\RubricSection;
use App\Models\RubricTemplate;
use App\Models\TeachingTip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricTest extends TestCase
{
    use RefreshDatabase;

    public function test_rubric_indicators_follow_pivot_position(): void
    {
        $rubric = Rubric::factory()->create();
        [$first, $second, $third] = Indicator::factory()->count(3)->create();

        $rubric->indicators()->attach([
            $first->id => ['position' => 3],
            $second->id => ['position' => 1],
            $third->id => ['position' => 2],
        ]);

        $this->assertSame(
            [$second->id, $third->id, $first->id],
            $rubric->indicators->pluck('id')->all(),
        );
    }

    public function test_template_exposes_sections_and_indicators_in_order(): void
    {
        $template = RubricTemplate::factory()->create();
        $section = RubricSection::factory()->for($template)->create();
        $later = Indicator::factory()->for($section)->create(['number' => 12]);
        $earlier = Indicator::factory()->for($section)->create(['number' => 11]);

        $this->assertSame([$earlier->id, $later->id], $section->indicators->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$earlier->id, $later->id], $template->indicators->pluck('id')->all());
    }

    public function test_indicator_returns_description_for_each_level(): void
    {
        $indicator = Indicator::factory()->create([
            'level_beginning' => 'Inicio',
            'level_developing' => 'Desarrollo',
            'level_achieved' => 'Logro',
            'level_outstanding' => 'Destaque',
        ]);

        $this->assertSame('Inicio', $indicator->levelDescription(AchievementLevel::Beginning));
        $this->assertSame('Desarrollo', $indicator->levelDescription(AchievementLevel::Developing));
        $this->assertSame('Logro', $indicator->levelDescription(AchievementLevel::Achieved));
        $this->assertSame('Destaque', $indicator->levelDescription(AchievementLevel::Outstanding));
    }

    public function test_indicator_teaching_tips_are_ordered_by_position(): void
    {
        $indicator = Indicator::factory()->create();
        $second = TeachingTip::factory()->for($indicator)->create(['position' => 2]);
        $first = TeachingTip::factory()->for($indicator)->create(['position' => 1]);

        $this->assertSame([$first->id, $second->id], $indicator->teachingTips->pluck('id')->all());
    }
}
