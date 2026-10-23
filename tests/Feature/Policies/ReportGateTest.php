<?php

namespace Tests\Feature\Policies;

use App\Models\Observation;
use App\Models\Rubric;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportGateTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->teacher = User::factory()->teacher()->for($this->school)->create();
    }

    public function test_school_admins_and_organization_admin_can_view_school_reports(): void
    {
        $admins = [
            User::factory()->principal()->for($this->school)->create(),
            User::factory()->curriculumHead()->for($this->school)->create(),
            User::factory()->organizationAdmin()->create(['organization_id' => $this->school->organization_id]),
        ];

        foreach ($admins as $admin) {
            $this->assertTrue($admin->can('viewSchoolReports', $this->school));
        }
    }

    public function test_teachers_and_foreign_admins_cannot_view_school_reports(): void
    {
        $otherSchool = School::factory()->create(['organization_id' => $this->school->organization_id]);
        $outsiders = [
            $this->teacher,
            User::factory()->teacher()->observer()->for($this->school)->create(),
            User::factory()->principal()->for($otherSchool)->create(),
            User::factory()->organizationAdmin()->create(),
        ];

        foreach ($outsiders as $outsider) {
            $this->assertFalse($outsider->can('viewSchoolReports', $this->school));
        }
    }

    public function test_teacher_can_view_only_their_own_report(): void
    {
        $colleague = User::factory()->teacher()->for($this->school)->create();

        $this->assertTrue($this->teacher->can('viewTeacherReport', $this->teacher));
        $this->assertFalse($colleague->can('viewTeacherReport', $this->teacher));
    }

    public function test_observer_needs_a_finished_observation_of_the_teacher(): void
    {
        $withFinished = User::factory()->teacher()->observer()->for($this->school)->create();
        $withInProgress = User::factory()->teacher()->observer()->for($this->school)->create();
        $this->observation($withFinished, finished: true);
        $this->observation($withInProgress, finished: false);

        $this->assertTrue($withFinished->can('viewTeacherReport', $this->teacher));
        $this->assertFalse($withInProgress->can('viewTeacherReport', $this->teacher));
    }

    public function test_school_admins_and_organization_admin_can_view_teacher_report(): void
    {
        $admins = [
            User::factory()->principal()->for($this->school)->create(),
            User::factory()->curriculumHead()->for($this->school)->create(),
            User::factory()->organizationAdmin()->create(['organization_id' => $this->school->organization_id]),
        ];

        foreach ($admins as $admin) {
            $this->assertTrue($admin->can('viewTeacherReport', $this->teacher));
        }
    }

    public function test_admins_of_another_school_or_organization_cannot_view_teacher_report(): void
    {
        $otherSchool = School::factory()->create(['organization_id' => $this->school->organization_id]);

        $this->assertFalse(User::factory()->principal()->for($otherSchool)->create()->can('viewTeacherReport', $this->teacher));
        $this->assertFalse(User::factory()->organizationAdmin()->create()->can('viewTeacherReport', $this->teacher));
    }

    private function observation(User $observer, bool $finished): void
    {
        $factory = Observation::factory()->for(Rubric::factory()->for($this->school));

        ($finished ? $factory->finished() : $factory)->create([
            'observer_id' => $observer->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }
}
