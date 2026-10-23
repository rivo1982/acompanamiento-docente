<?php

namespace Tests\Feature\Models;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\School;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbd_is_unique(): void
    {
        School::factory()->create(['rbd' => '12345-6']);

        $this->expectException(UniqueConstraintViolationException::class);

        School::factory()->create(['rbd' => '12345-6']);
    }

    public function test_school_belongs_to_a_typed_organization(): void
    {
        $organization = Organization::factory()->create(['type' => OrganizationType::Slep]);
        $school = School::factory()->for($organization)->create();

        $this->assertSame(OrganizationType::Slep, $school->organization->type);
        $this->assertSame('SLEP', $school->organization->type->label());
        $this->assertTrue($organization->schools->contains($school));
    }
}
