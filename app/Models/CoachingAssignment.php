<?php

namespace App\Models;

use App\Exceptions\InvalidCoachingAssignmentException;
use Database\Factories\CoachingAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Observer → teacher assignment (asignación).
 */
#[Fillable(['school_id', 'observer_id', 'teacher_id'])]
class CoachingAssignment extends Model
{
    /** @use HasFactory<CoachingAssignmentFactory> */
    use HasFactory;

    /**
     * Reject assignments whose observer and teacher are not a valid pair of the school.
     */
    protected static function booted(): void
    {
        static::saving(function (CoachingAssignment $assignment): void {
            $school = School::findOrFail($assignment->school_id);
            $observer = User::findOrFail($assignment->observer_id);
            $teacher = User::findOrFail($assignment->teacher_id);

            if (! self::isValidPair($school, $observer, $teacher)) {
                throw InvalidCoachingAssignmentException::for($school, $observer, $teacher);
            }
        });
    }

    /**
     * Whether the observer may be assigned to the teacher within the given school.
     */
    public static function isValidPair(School $school, User $observer, User $teacher): bool
    {
        return $observer->is_observer
            && ! $observer->is($teacher)
            && $observer->isStaffOf($school)
            && $teacher->isStaffOf($school);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
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
}
