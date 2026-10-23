<?php

namespace Tests\Feature\Policies;

use App\Models\CoachingAssignment;
use App\Models\Observation;
use App\Models\Rubric;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $observer;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->observer = User::factory()->teacher()->observer()->for($this->school)->create();
        $this->teacher = User::factory()->teacher()->for($this->school)->create();
    }

    public function test_assigned_observer_of_the_same_school_can_create(): void
    {
        $this->assign($this->observer, $this->teacher);

        $this->assertTrue($this->observer->can('create', [Observation::class, $this->teacher]));
    }

    public function test_observer_without_assignment_cannot_create(): void
    {
        $principal = User::factory()->principal()->for($this->school)->create();

        $this->assertFalse($this->observer->can('create', [Observation::class, $this->teacher]));
        $this->assertFalse($principal->can('create', [Observation::class, $this->teacher]));
    }

    public function test_assigned_user_no_longer_flagged_as_observer_cannot_create(): void
    {
        $this->assign($this->observer, $this->teacher);
        $this->observer->update(['is_observer' => false]);

        $this->assertFalse($this->observer->can('create', [Observation::class, $this->teacher]));
    }

    public function test_assigned_observer_cannot_create_once_the_teacher_moved_to_another_school(): void
    {
        $this->assign($this->observer, $this->teacher);
        $this->teacher->update(['school_id' => School::factory()->create()->id]);

        $this->assertFalse($this->observer->can('create', [Observation::class, $this->teacher]));
    }

    public function test_observer_can_update_score_and_finish_only_while_in_progress(): void
    {
        $inProgress = $this->observation();
        $finished = $this->observation(finished: true);

        foreach (['update', 'score', 'finish'] as $ability) {
            $this->assertTrue($this->observer->can($ability, $inProgress));
            $this->assertFalse($this->observer->can($ability, $finished));
        }
    }

    public function test_only_the_observer_can_update_score_and_finish(): void
    {
        $observation = $this->observation();
        $principal = User::factory()->principal()->for($this->school)->create();
        $organizationAdmin = User::factory()->organizationAdmin()->create(['organization_id' => $this->school->organization_id]);

        foreach ([$this->teacher, $principal, $organizationAdmin] as $user) {
            foreach (['update', 'score', 'finish'] as $ability) {
                $this->assertFalse($user->can($ability, $observation));
            }
        }
    }

    public function test_observer_can_view_and_export_at_any_status(): void
    {
        foreach ([$this->observation(), $this->observation(finished: true)] as $observation) {
            $this->assertTrue($this->observer->can('view', $observation));
            $this->assertTrue($this->observer->can('export', $observation));
        }
    }

    public function test_teacher_can_view_and_export_only_when_finished(): void
    {
        $inProgress = $this->observation();
        $finished = $this->observation(finished: true);

        $this->assertFalse($this->teacher->can('view', $inProgress));
        $this->assertFalse($this->teacher->can('export', $inProgress));
        $this->assertTrue($this->teacher->can('view', $finished));
        $this->assertTrue($this->teacher->can('export', $finished));
    }

    public function test_school_admins_and_organization_admin_can_view_and_export(): void
    {
        $observation = $this->observation();
        $admins = [
            User::factory()->principal()->for($this->school)->create(),
            User::factory()->curriculumHead()->for($this->school)->create(),
            User::factory()->organizationAdmin()->create(['organization_id' => $this->school->organization_id]),
        ];

        foreach ($admins as $admin) {
            $this->assertTrue($admin->can('view', $observation));
            $this->assertTrue($admin->can('export', $observation));
        }
    }

    public function test_other_staff_and_foreign_admins_cannot_view(): void
    {
        $observation = $this->observation(finished: true);
        $otherSchool = School::factory()->create(['organization_id' => $this->school->organization_id]);
        $outsiders = [
            User::factory()->teacher()->for($this->school)->create(),
            User::factory()->teacher()->observer()->for($this->school)->create(),
            User::factory()->principal()->for($otherSchool)->create(),
            User::factory()->organizationAdmin()->create(),
        ];

        foreach ($outsiders as $outsider) {
            $this->assertFalse($outsider->can('view', $observation));
            $this->assertFalse($outsider->can('export', $observation));
        }
    }

    private function assign(User $observer, User $teacher): void
    {
        CoachingAssignment::factory()->for($this->school)->create(['observer_id' => $observer->id, 'teacher_id' => $teacher->id]);
    }

    private function observation(bool $finished = false): Observation
    {
        $factory = Observation::factory()->for(Rubric::factory()->for($this->school));

        return ($finished ? $factory->finished() : $factory)->create([
            'observer_id' => $this->observer->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }
}
