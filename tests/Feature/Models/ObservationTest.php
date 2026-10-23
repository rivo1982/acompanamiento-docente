<?php

namespace Tests\Feature\Models;

use App\Enums\AchievementLevel;
use App\Enums\ObservationStatus;
use App\Models\Indicator;
use App\Models\Observation;
use App\Models\ObservationScore;
use App\Models\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_observation_is_in_progress_with_rubric_snapshot(): void
    {
        $rubric = Rubric::factory()->create(['name' => 'Pauta primer ciclo']);
        $indicator = Indicator::factory()->create(['number' => 7, 'name' => 'Clima de aula']);
        $rubric->indicators()->attach($indicator, ['position' => 1]);

        $observation = Observation::factory()->for($rubric)->create();

        $this->assertSame(ObservationStatus::InProgress, $observation->fresh()->status);
        $this->assertSame('Pauta primer ciclo', $observation->rubric_snapshot['name']);
        $this->assertSame(7, $observation->rubric_snapshot['indicators'][0]['number']);
        $this->assertSame('Clima de aula', $observation->rubric_snapshot['indicators'][0]['name']);
    }

    public function test_score_feedback_is_frozen_when_catalog_changes_after_start(): void
    {
        $rubric = Rubric::factory()->create(['name' => 'Original']);
        $indicator = Indicator::factory()->create([
            'feedback_meaning' => 'Significado original',
            'feedback_to_achieved' => 'Para lograr original',
            'feedback_to_outstanding' => 'Para destacar original',
            'sources' => 'Fuente original',
        ]);
        $rubric->indicators()->attach($indicator, ['position' => 1]);
        $observation = Observation::factory()->for($rubric)->create();
        $score = ObservationScore::factory()->for($observation)->create([
            'indicator_id' => $indicator->id,
            'level' => AchievementLevel::Achieved,
        ]);

        $indicator->update(['feedback_meaning' => 'Editado', 'sources' => 'Editado']);
        $rubric->update(['name' => 'Renombrada']);

        $this->assertSame([
            'meaning' => 'Significado original',
            'to_achieved' => 'Para lograr original',
            'to_outstanding' => 'Para destacar original',
            'sources' => 'Fuente original',
            'congratulations' => 'Felicitaciones',
        ], $score->fresh()->automaticFeedback());
        $this->assertSame('Original', $observation->fresh()->rubric_snapshot['name']);
    }

    public function test_feedback_has_no_congratulations_below_achieved(): void
    {
        $rubric = Rubric::factory()->create();
        $indicator = Indicator::factory()->create();
        $rubric->indicators()->attach($indicator, ['position' => 1]);
        $observation = Observation::factory()->for($rubric)->create();

        $score = ObservationScore::factory()->for($observation)->create([
            'indicator_id' => $indicator->id,
            'level' => AchievementLevel::Developing,
        ]);

        $this->assertNull($score->automaticFeedback()['congratulations']);
    }

    public function test_finish_records_whole_minutes_and_status(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $observation = Observation::factory()->create(['started_at' => now()]);

        $this->travel(47)->minutes();
        $this->travel(35)->seconds();
        $observation->finish();

        $observation->refresh();
        $this->assertSame(ObservationStatus::Finished, $observation->status);
        $this->assertSame(47, $observation->duration_minutes);
        $this->assertTrue($observation->finished_at->equalTo(now()));
    }
}
