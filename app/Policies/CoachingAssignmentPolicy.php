<?php

namespace App\Policies;

use App\Models\CoachingAssignment;
use App\Models\School;
use App\Models\User;

class CoachingAssignmentPolicy
{
    /**
     * Only school admins of the school may list its assignments.
     */
    public function viewAny(User $user, School $school): bool
    {
        return $user->isSchoolAdminOf($school);
    }

    /**
     * School admins may assign a flagged observer to a different teacher of the same school.
     */
    public function create(User $user, School $school, User $observer, User $teacher): bool
    {
        return $user->isSchoolAdminOf($school)
            && CoachingAssignment::isValidPair($school, $observer, $teacher);
    }

    /**
     * Determine whether the user can delete the assignment.
     */
    public function delete(User $user, CoachingAssignment $coachingAssignment): bool
    {
        return $user->isSchoolAdminOf($coachingAssignment->school);
    }
}
