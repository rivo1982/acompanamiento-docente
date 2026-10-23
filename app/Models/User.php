<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StaffRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'organization_id', 'school_id', 'rut', 'role', 'is_observer'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Defaults mirrored from the migration so the saving rule sees them before insert.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'docente',
        'is_observer' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => StaffRole::class,
            'is_observer' => 'boolean',
        ];
    }

    /**
     * Principals and curriculum heads are always observers. Leaving that role always drops
     * the flag; an admin may flag the user as an observer again in a separate save.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role->isSchoolAdmin()) {
                $user->is_observer = true;

                return;
            }

            if ($user->wasDemotedFromSchoolAdmin()) {
                $user->is_observer = false;
            }
        });
    }

    /**
     * Whether the pending save moves this user out of a school admin role.
     */
    protected function wasDemotedFromSchoolAdmin(): bool
    {
        $originalRole = $this->getOriginal('role');

        return $this->exists
            && $this->isDirty('role')
            && $originalRole instanceof StaffRole
            && $originalRole->isSchoolAdmin();
    }

    /**
     * Whether the user belongs to the given school's staff, in any role.
     */
    public function isStaffOf(School $school): bool
    {
        return self::sameKey($this->school_id, $school->getKey());
    }

    /**
     * Whether the user is a principal or curriculum head of the given school.
     */
    public function isSchoolAdminOf(School $school): bool
    {
        return $this->role->isSchoolAdmin() && $this->isStaffOf($school);
    }

    /**
     * Whether the user administers the organization that owns the given school.
     */
    public function isOrganizationAdminOf(School $school): bool
    {
        return $this->administersOrganization($school->organization_id);
    }

    /**
     * Whether the user administers the organization the given staff member belongs to.
     */
    public function isOrganizationAdminOfStaff(User $staff): bool
    {
        return $this->administersOrganization($staff->organization_id);
    }

    /**
     * Whether the user is a school admin of the staff member's school.
     */
    public function isSchoolAdminOfStaff(User $staff): bool
    {
        return $this->role->isSchoolAdmin() && self::sameKey($this->school_id, $staff->school_id);
    }

    /**
     * Whether the user may observe the given teacher: flagged observer, same school and assigned.
     */
    public function canObserve(User $teacher): bool
    {
        return $this->is_observer
            && self::sameKey($this->school_id, $teacher->school_id)
            && CoachingAssignment::query()
                ->where('observer_id', $this->getKey())
                ->where('teacher_id', $teacher->getKey())
                ->where('school_id', $teacher->school_id)
                ->exists();
    }

    /**
     * Whether the user is an organization admin of the given organization id.
     */
    protected function administersOrganization(int|string|null $organizationId): bool
    {
        return $this->role === StaffRole::OrganizationAdmin
            && self::sameKey($this->organization_id, $organizationId);
    }

    /**
     * Compare two foreign keys, treating null as never matching.
     */
    protected static function sameKey(int|string|null $first, int|string|null $second): bool
    {
        return $first !== null && $second !== null && (string) $first === (string) $second;
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Teachers this user is assigned to observe.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignedTeachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coaching_assignments', 'observer_id', 'teacher_id')
            ->withPivot('school_id')
            ->withTimestamps();
    }

    /**
     * Observers assigned to this teacher.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignedObservers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coaching_assignments', 'teacher_id', 'observer_id')
            ->withPivot('school_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Observation, $this>
     */
    public function observationsMade(): HasMany
    {
        return $this->hasMany(Observation::class, 'observer_id');
    }

    /**
     * @return HasMany<Observation, $this>
     */
    public function observationsReceived(): HasMany
    {
        return $this->hasMany(Observation::class, 'teacher_id');
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function observers(Builder $query): void
    {
        $query->where('is_observer', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function teachers(Builder $query): void
    {
        $query->where('role', StaffRole::Teacher);
    }
}
