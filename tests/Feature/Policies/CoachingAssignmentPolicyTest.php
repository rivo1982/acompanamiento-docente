<?php

namespace Tests\Feature\Policies;

use App\Exceptions\InvalidCoachingAssignmentException;
use App\Models\CoachingAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingAssignmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $principal;

    private User $observer;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->principal = User::factory()->principal()->for($this->school)->create();
        $this->observer = User::factory()->teacher()->observer()->for($this->school)->create();
        $this->teacher = User::factory()->teacher()->for($this->school)->create();
    }

    public function test_school_admins_can_list_create_and_delete_assignments(): void
    {
        $assignment = CoachingAssignment::factory()->for($this->school)->create(['observer_id' => $this->observer->id, 'teacher_id' => $this->teacher->id]);

        foreach ([$this->principal, User::factory()->curriculumHead()->for($this->school)->create()] as $admin) {
            $this->assertTrue($admin->can('viewAny', [CoachingAssignment::class, $this->school]));
            $this->assertTrue($admin->can('create', [CoachingAssignment::class, $this->school, $this->observer, $this->teacher]));
            $this->assertTrue($admin->can('delete', $assignment));
        }
    }

    public function test_teachers_observers_and_organization_admins_are_denied(): void
    {
        $assignment = CoachingAssignment::factory()->for($this->school)->create(['observer_id' => $this->observer->id, 'teacher_id' => $this->teacher->id]);
        $organizationAdmin = User::factory()->organizationAdmin()->create(['organization_id' => $this->school->organization_id]);

        foreach ([$this->teacher, $this->observer, $organizationAdmin] as $user) {
            $this->assertFalse($user->can('viewAny', [CoachingAssignment::class, $this->school]));
            $this->assertFalse($user->can('create', [CoachingAssignment::class, $this->school, $this->observer, $this->teacher]));
            $this->assertFalse($user->can('delete', $assignment));
        }
    }

    public function test_admin_of_another_school_is_denied(): void
    {
        $assignment = CoachingAssignment::factory()->for($this->school)->create(['observer_id' => $this->observer->id, 'teacher_id' => $this->teacher->id]);
        $otherSchool = School::factory()->create(['organization_id' => $this->school->organization_id]);
        $otherPrincipal = User::factory()->principal()->for($otherSchool)->create();

        $this->assertFalse($otherPrincipal->can('viewAny', [CoachingAssignment::class, $this->school]));
        $this->assertFalse($otherPrincipal->can('create', [CoachingAssignment::class, $this->school, $this->observer, $this->teacher]));
        $this->assertFalse($otherPrincipal->can('delete', $assignment));
    }

    public function test_create_is_denied_when_the_observer_is_not_flagged(): void
    {
        $unflagged = User::factory()->teacher()->for($this->school)->create();

        $this->assertFalse($this->principal->can('create', [CoachingAssignment::class, $this->school, $unflagged, $this->teacher]));
    }

    public function test_create_is_denied_when_observer_or_teacher_belong_to_another_school(): void
    {
        $otherSchool = School::factory()->create(['organization_id' => $this->school->organization_id]);
        $foreignObserver = User::factory()->principal()->for($otherSchool)->create();
        $foreignTeacher = User::factory()->teacher()->for($otherSchool)->create();

        $this->assertFalse($this->principal->can('create', [CoachingAssignment::class, $this->school, $foreignObserver, $this->teacher]));
        $this->assertFalse($this->principal->can('create', [CoachingAssignment::class, $this->school, $this->observer, $foreignTeacher]));
    }

    public function test_create_is_denied_when_observer_and_teacher_are_the_same_person(): void
    {
        $this->assertFalse($this->principal->can('create', [CoachingAssignment::class, $this->school, $this->observer, $this->observer]));
    }

    public function test_assignment_with_a_teacher_from_another_school_cannot_be_stored(): void
    {
        $foreignTeacher = User::factory()->teacher()->create();

        $this->expectException(InvalidCoachingAssignmentException::class);

        CoachingAssignment::factory()->for($this->school)->create(['observer_id' => $this->observer->id, 'teacher_id' => $foreignTeacher->id]);
    }

    public function test_assignment_with_an_unflagged_observer_cannot_be_stored(): void
    {
        $unflagged = User::factory()->teacher()->for($this->school)->create();

        $this->expectException(InvalidCoachingAssignmentException::class);

        CoachingAssignment::factory()->for($this->school)->create(['observer_id' => $unflagged->id, 'teacher_id' => $this->teacher->id]);
    }

    public function test_assignment_of_a_teacher_to_themself_cannot_be_stored(): void
    {
        $this->expectException(InvalidCoachingAssignmentException::class);

        CoachingAssignment::factory()->for($this->school)->create(['observer_id' => $this->observer->id, 'teacher_id' => $this->observer->id]);
    }
}
