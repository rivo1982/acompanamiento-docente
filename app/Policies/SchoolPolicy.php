<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    /**
     * School admins of the school and the admin of its organization may view it.
     */
    public function view(User $user, School $school): bool
    {
        return $user->isSchoolAdminOf($school) || $user->isOrganizationAdminOf($school);
    }

    /**
     * Only school admins of the school may import or edit its staff.
     */
    public function manageStaff(User $user, School $school): bool
    {
        return $user->isSchoolAdminOf($school);
    }
}
