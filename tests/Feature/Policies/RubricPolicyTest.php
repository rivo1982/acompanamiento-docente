<?php

namespace Tests\Feature\Policies;

use App\Models\Rubric;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricPolicyTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Rubric $rubric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->rubric = Rubric::factory()->for($this->school)->create();
    }

    public function test_school_admins_can_read_export_and_edit(): void
    {
        foreach ([User::factory()->principal()->for($this->school)->create(), User::factory()->curriculumHead()->for($this->school)->create()] as $admin) {
            $this->assertTrue($admin->can('viewAny', [Rubric::class, $this->school]));
            $this->assertTrue($admin->can('view', $this->rubric));
            $this->assertTrue($admin->can('export', $this->rubric));
            $this->assertTrue($admin->can('create', [Rubric::class, $this->school]));
            $this->assertTrue($admin->can('update', $this->rubric));
            $this->assertTrue($admin->can('delete', $this->rubric));
        }
    }

    public function test_teachers_of_the_school_can_read_and_export_but_not_edit(): void
    {
        foreach ([User::factory()->teacher()->for($this->school)->create(), User::factory()->teacher()->observer()->for($this->school)->create()] as $teacher) {
            $this->assertTrue($teacher->can('viewAny', [Rubric::class, $this->school]));
            $this->assertTrue($teacher->can('view', $this->rubric));
            $this->assertTrue($teacher->can('export', $this->rubric));
            $this->assertFalse($teacher->can('create', [Rubric::class, $this->school]));
            $this->assertFalse($teacher->can('update', $this->rubric));
            $this->assertFalse($teacher->can('delete', $this->rubric));
        }
    }

    public function test_organization_admin_can_read_but_not_edit(): void
    {
        $organizationAdmin = User::factory()->organizationAdmin()->create(['organization_id' => $this->school->organization_id]);

        $this->assertTrue($organizationAdmin->can('viewAny', [Rubric::class, $this->school]));
        $this->assertTrue($organizationAdmin->can('view', $this->rubric));
        $this->assertFalse($organizationAdmin->can('create', [Rubric::class, $this->school]));
        $this->assertFalse($organizationAdmin->can('update', $this->rubric));
        $this->assertFalse($organizationAdmin->can('delete', $this->rubric));
    }

    public function test_staff_of_another_school_is_denied(): void
    {
        $otherSchool = School::factory()->create(['organization_id' => $this->school->organization_id]);

        foreach ([User::factory()->principal()->for($otherSchool)->create(), User::factory()->teacher()->for($otherSchool)->create()] as $outsider) {
            $this->assertFalse($outsider->can('viewAny', [Rubric::class, $this->school]));
            $this->assertFalse($outsider->can('view', $this->rubric));
            $this->assertFalse($outsider->can('export', $this->rubric));
            $this->assertFalse($outsider->can('create', [Rubric::class, $this->school]));
            $this->assertFalse($outsider->can('update', $this->rubric));
            $this->assertFalse($outsider->can('delete', $this->rubric));
        }
    }

    public function test_organization_admin_of_another_organization_is_denied(): void
    {
        $foreignAdmin = User::factory()->organizationAdmin()->create();

        $this->assertFalse($foreignAdmin->can('viewAny', [Rubric::class, $this->school]));
        $this->assertFalse($foreignAdmin->can('view', $this->rubric));
        $this->assertFalse($foreignAdmin->can('export', $this->rubric));
    }
}
