<?php

namespace App\Models;

use App\Enums\ObservationStatus;
use App\Exceptions\ObservationAlreadyFinishedException;
use Database\Factories\ObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Classroom observation (acompañamiento).
 */
#[Fillable([
    'school_id', 'rubric_id', 'observer_id', 'teacher_id',
    'subject', 'education_level', 'course',
    'started_at', 'finished_at', 'duration_minutes', 'status', 'rubric_snapshot',
])]
class Observation extends Model
{
    /** @use HasFactory<ObservationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'en_curso',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => ObservationStatus::class,
            'rubric_snapshot' => 'array',
        ];
    }

    /**
     * Freeze the rubric content and start time when the observation is created.
     */
    protected static function booted(): void
    {
        static::creating(function (Observation $observation): void {
            $observation->started_at ??= now();

            if ($observation->rubric_snapshot === null) {
                $observation->rubric_snapshot = $observation->rubric->toSnapshot();
            }
        });
    }

    /**
     * Close the observation, recording its end time and whole-minute duration.
     *
     * @throws ObservationAlreadyFinishedException
     */
    public function finish(): void
    {
        if ($this->status === ObservationStatus::Finished) {
            throw ObservationAlreadyFinishedException::for($this);
        }

        $finishedAt = now();

        $this->forceFill([
            'finished_at' => $finishedAt,
            'duration_minutes' => (int) floor($this->started_at->diffInMinutes($finishedAt, true)),
            'status' => ObservationStatus::Finished,
        ])->save();
    }

    /**
     * Snapshot entry for the given indicator, or null if it was not part of the rubric.
     *
     * @return array<string, mixed>|null
     */
    public function snapshotIndicator(int $indicatorId): ?array
    {
        return collect($this->rubric_snapshot['indicators'] ?? [])
            ->firstWhere('id', $indicatorId);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Rubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function observer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'observer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<ObservationScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(ObservationScore::class);
    }
}
