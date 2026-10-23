<?php

namespace Tests\Feature\Models;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_principal_is_always_an_observer(): void
    {
        $principal = User::factory()->principal()->create(['is_observer' => false]);

        $this->assertTrue($principal->fresh()->is_observer);
    }

    public function test_curriculum_head_is_always_an_observer(): void
    {
        $curriculumHead = User::factory()->curriculumHead()->create(['is_observer' => false]);

        $this->assertTrue($curriculumHead->fresh()->is_observer);
    }

    public function test_school_admin_cannot_be_unflagged_as_observer(): void
    {
        $principal = User::factory()->principal()->create();

        $principal->update(['is_observer' => false]);

        $this->assertTrue($principal->fresh()->is_observer);
    }

    public function test_teacher_is_not_an_observer_by_default(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->assertSame(StaffRole::Teacher, $teacher->fresh()->role);
        $this->assertFalse($teacher->fresh()->is_observer);
    }

    public function test_teacher_can_be_flagged_as_observer(): void
    {
        $teacher = User::factory()->teacher()->observer()->create();

        $this->assertTrue($teacher->fresh()->is_observer);
    }

    public function test_user_defaults_to_teacher_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(StaffRole::Teacher, $user->fresh()->role);
    }

    public function test_observers_and_teachers_scopes(): void
    {
        $principal = User::factory()->principal()->create();
        $observerTeacher = User::factory()->teacher()->observer()->create();
        $teacher = User::factory()->teacher()->create();

        $this->assertEqualsCanonicalizing(
            [$principal->id, $observerTeacher->id],
            User::observers()->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$observerTeacher->id, $teacher->id],
            User::teachers()->pluck('id')->all(),
        );
    }
}
