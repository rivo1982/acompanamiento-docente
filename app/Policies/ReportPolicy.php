<?php

namespace App\Policies;

use App\Enums\ObservationStatus;
use App\Models\School;
use App\Models\User;

/**
 * Report visibility (reports 6.1–6.4), registered as gates in AppServiceProvider.
 */
class ReportPolicy
{
    /**
     * School-level reports (6.1–6.3): school admins of the school and its organization admin.
     */
    public function viewSchoolReports(User $user, School $school): bool
    {
        return $user->isSchoolAdminOf($school) || $user->isOrganizationAdminOf($school);
    }

    /**
     * Teacher report (6.4): the teacher, an observer with a finished observation of them,
     * school admins of the teacher's school and the teacher's organization admin.
     */
    public function viewTeacherReport(User $user, User $teacher): bool
    {
        return $user->is($teacher)
            || $user->isSchoolAdminOfStaff($teacher)
            || $user->isOrganizationAdminOfStaff($teacher)
            || $user->observationsMade()
                ->where('teacher_id', $teacher->getKey())
                ->where('status', ObservationStatus::Finished)
                ->exists();
    }
}
