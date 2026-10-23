<?php

namespace Tests\Feature\Models;

use App\Enums\AchievementLevel;
use App\Enums\ObservationStatus;
use App\Exceptions\IndicatorNotInSnapshotException;
use App\Exceptions\ObservationAlreadyFinishedException;
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

    public function test_finishing_twice_is_rejected_and_keeps_the_original_record(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $observation = Observation::factory()->create(['started_at' => now()]);
        $this->travel(30)->minutes();
        $observation->finish();
        $finishedAt = $observation->fresh()->finished_at;

        $this->travel(20)->minutes();

        try {
            $observation->finish();
            $this->fail('Expected ObservationAlreadyFinishedException.');
        } catch (ObservationAlreadyFinishedException) {
            $observation->refresh();
            $this->assertSame(30, $observation->duration_minutes);
            $this->assertTrue($observation->finished_at->equalTo($finishedAt));
        }
    }

    public function test_provided_snapshot_and_start_time_are_not_overwritten(): void
    {
        $startedAt = now()->subHour()->startOfMinute();
        $snapshot = ['id' => 0, 'name' => 'Provista', 'indicators' => []];

        $observation = Observation::factory()->create([
            'started_at' => $startedAt,
            'rubric_snapshot' => $snapshot,
        ]);

        $observation->refresh();
        $this->assertSame($snapshot, $observation->rubric_snapshot);
        $this->assertTrue($observation->started_at->equalTo($startedAt));
    }

    public function test_automatic_feedback_fails_when_indicator_is_missing_from_snapshot(): void
    {
        $observation = Observation::factory()->create();
        $foreignIndicator = Indicator::factory()->create();
        $score = ObservationScore::factory()->for($observation)->create([
            'indicator_id' => $foreignIndicator->id,
            'level' => AchievementLevel::Achieved,
        ]);

        $this->expectException(IndicatorNotInSnapshotException::class);

        $score->automaticFeedback();
    }

    public function test_finishing_from_a_stale_copy_is_rejected_and_keeps_the_first_finish(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $observation = Observation::factory()->create(['started_at' => now()]);
        $firstCopy = Observation::findOrFail($observation->id);
        $staleCopy = Observation::findOrFail($observation->id);
        $this->travel(30)->minutes();
        $firstCopy->finish();
        $this->travel(20)->minutes();

        try {
            $staleCopy->finish();
            $this->fail('Expected ObservationAlreadyFinishedException.');
        } catch (ObservationAlreadyFinishedException) {
            $stored = $observation->fresh();
            $this->assertSame(30, $stored->duration_minutes);
            $this->assertTrue($stored->finished_at->equalTo($firstCopy->finished_at));
        }
    }

    public function test_finish_refreshes_the_model_with_the_stored_values(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $observation = Observation::factory()->create(['started_at' => now()]);
        $this->travel(12)->minutes();

        $observation->finish();

        $this->assertSame(ObservationStatus::Finished, $observation->status);
        $this->assertSame(12, $observation->duration_minutes);
        $this->assertFalse($observation->isDirty());
    }

    public function test_automatic_feedback_fails_when_snapshot_entry_lacks_feedback_keys(): void
    {
        $indicator = Indicator::factory()->create();
        $observation = Observation::factory()->create([
            'rubric_snapshot' => [
                'id' => 0,
                'name' => 'Incompleta',
                'indicators' => [['id' => $indicator->id, 'number' => $indicator->number, 'name' => $indicator->name]],
            ],
        ]);
        $score = ObservationScore::factory()->for($observation)->create([
            'indicator_id' => $indicator->id,
            'level' => AchievementLevel::Achieved,
        ]);

        $this->expectException(IndicatorNotInSnapshotException::class);
        $this->expectExceptionMessage('missing [feedback_meaning, feedback_to_achieved, sources]');

        $score->automaticFeedback();
    }
}
