<?php

namespace App\Models;

use App\Enums\AchievementLevel;
use Database\Factories\ObservationScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Level assigned to one indicator within an observation.
 */
#[Fillable(['observation_id', 'indicator_id', 'level', 'personal_feedback'])]
class ObservationScore extends Model
{
    /** @use HasFactory<ObservationScoreFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => AchievementLevel::class,
        ];
    }

    /**
     * Automatic feedback read from the observation's frozen rubric snapshot.
     *
     * @return array{meaning: string, to_achieved: string, to_outstanding: ?string, sources: string, congratulations: ?string}
     */
    public function automaticFeedback(): array
    {
        $indicator = $this->observation->snapshotIndicator($this->indicator_id) ?? [];

        return [
            'meaning' => $indicator['feedback_meaning'] ?? '',
            'to_achieved' => $indicator['feedback_to_achieved'] ?? '',
            'to_outstanding' => $indicator['feedback_to_outstanding'] ?? null,
            'sources' => $indicator['sources'] ?? '',
            'congratulations' => $this->level->congratulationMessage(),
        ];
    }

    /**
     * @return BelongsTo<Observation, $this>
     */
    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    /**
     * @return BelongsTo<Indicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }
}
