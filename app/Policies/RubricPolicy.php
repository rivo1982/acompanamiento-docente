<?php

namespace App\Policies;

use App\Models\Rubric;
use App\Models\School;
use App\Models\User;

class RubricPolicy
{
    /**
     * Any staff member of the school and the admin of its organization may list its rubrics.
     */
    public function viewAny(User $user, School $school): bool
    {
        return $user->isStaffOf($school) || $user->isOrganizationAdminOf($school);
    }

    /**
     * Determine whether the user can view the rubric.
     */
    public function view(User $user, Rubric $rubric): bool
    {
        return $this->viewAny($user, $rubric->school);
    }

    /**
     * Exporting the rubric PDF follows the same visibility as viewing it.
     */
    public function export(User $user, Rubric $rubric): bool
    {
        return $this->view($user, $rubric);
    }

    /**
     * Only school admins of the school may create rubrics.
     */
    public function create(User $user, School $school): bool
    {
        return $user->isSchoolAdminOf($school);
    }

    /**
     * Determine whether the user can update the rubric.
     */
    public function update(User $user, Rubric $rubric): bool
    {
        return $user->isSchoolAdminOf($rubric->school);
    }

    /**
     * Determine whether the user can delete the rubric.
     */
    public function delete(User $user, Rubric $rubric): bool
    {
        return $user->isSchoolAdminOf($rubric->school);
    }
}
