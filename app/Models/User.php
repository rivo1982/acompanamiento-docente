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
     * Principals and curriculum heads are always observers.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role->isSchoolAdmin()) {
                $user->is_observer = true;
            }
        });
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
