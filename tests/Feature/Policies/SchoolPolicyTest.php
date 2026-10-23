<?php

namespace Tests\Feature\Policies;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolPolicyTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
    }

    public function test_school_admins_can_view_and_manage_staff(): void
    {
        foreach ([User::factory()->principal()->for($this->school)->create(), User::factory()->curriculumHead()->for($this->school)->create()] as $admin) {
            $this->assertTrue($admin->can('view', $this->school));
            $this->assertTrue($admin->can('manageStaff', $this->school));
        }
    }

    public function test_organization_admin_can_view_but_not_manage_staff(): void
    {
        $organizationAdmin = User::factory()->organizationAdmin()->create(['organization_id' => $this->school->organization_id]);

        $this->assertTrue($organizationAdmin->can('view', $this->school));
        $this->assertFalse($organizationAdmin->can('manageStaff', $this->school));
    }

    public function test_teachers_and_observer_teachers_are_denied(): void
    {
        foreach ([User::factory()->teacher()->for($this->school)->create(), User::factory()->teacher()->observer()->for($this->school)->create()] as $teacher) {
            $this->assertFalse($teacher->can('view', $this->school));
            $this->assertFalse($teacher->can('manageStaff', $this->school));
        }
    }

    public function test_admin_of_another_school_in_the_same_organization_is_denied(): void
    {
        $otherSchool = School::factory()->create(['organization_id' => $this->school->organization_id]);
        $otherPrincipal = User::factory()->principal()->for($otherSchool)->create();

        $this->assertFalse($otherPrincipal->can('view', $this->school));
        $this->assertFalse($otherPrincipal->can('manageStaff', $this->school));
    }

    public function test_organization_admin_of_another_organization_is_denied(): void
    {
        $foreignAdmin = User::factory()->organizationAdmin()->create();

        $this->assertFalse($foreignAdmin->can('view', $this->school));
    }
}
