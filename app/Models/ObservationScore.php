<?php

namespace App\Models;

use App\Enums\AchievementLevel;
use App\Exceptions\IndicatorNotInSnapshotException;
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
     * Snapshot keys that automatic feedback cannot be built without.
     *
     * @var list<string>
     */
    private const REQUIRED_SNAPSHOT_KEYS = ['feedback_meaning', 'feedback_to_achieved', 'sources'];

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
     *
     * @throws IndicatorNotInSnapshotException
     */
    public function automaticFeedback(): array
    {
        $indicator = $this->observation->snapshotIndicator($this->indicator_id)
            ?? throw IndicatorNotInSnapshotException::for($this->observation, $this->indicator_id);

        $missingKeys = array_values(array_diff(self::REQUIRED_SNAPSHOT_KEYS, array_keys($indicator)));

        if ($missingKeys !== []) {
            throw IndicatorNotInSnapshotException::incomplete($this->observation, $this->indicator_id, $missingKeys);
        }

        return [
            'meaning' => $indicator['feedback_meaning'],
            'to_achieved' => $indicator['feedback_to_achieved'],
            'to_outstanding' => $indicator['feedback_to_outstanding'] ?? null,
            'sources' => $indicator['sources'],
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
