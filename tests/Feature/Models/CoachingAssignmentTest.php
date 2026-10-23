<?php

namespace Tests\Feature\Models;

use App\Models\CoachingAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_links_observer_and_teacher_in_both_directions(): void
    {
        $school = School::factory()->create();
        $observer = User::factory()->principal()->for($school)->create();
        $firstTeacher = User::factory()->teacher()->for($school)->create();
        $secondTeacher = User::factory()->teacher()->for($school)->create();

        CoachingAssignment::factory()->for($school)->create(['observer_id' => $observer->id, 'teacher_id' => $firstTeacher->id]);
        CoachingAssignment::factory()->for($school)->create(['observer_id' => $observer->id, 'teacher_id' => $secondTeacher->id]);

        $this->assertEqualsCanonicalizing(
            [$firstTeacher->id, $secondTeacher->id],
            $observer->assignedTeachers->pluck('id')->all(),
        );
        $this->assertSame([$observer->id], $firstTeacher->assignedObservers->pluck('id')->all());
        $this->assertSame(2, $school->coachingAssignments()->count());
    }

    public function test_same_observer_cannot_be_assigned_twice_to_the_same_teacher(): void
    {
        $assignment = CoachingAssignment::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        CoachingAssignment::factory()->create([
            'school_id' => $assignment->school_id,
            'observer_id' => $assignment->observer_id,
            'teacher_id' => $assignment->teacher_id,
        ]);
    }
}
