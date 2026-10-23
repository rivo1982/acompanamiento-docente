<?php

namespace App\Policies;

use App\Enums\ObservationStatus;
use App\Models\Observation;
use App\Models\User;

class ObservationPolicy
{
    /**
     * Only an assigned, flagged observer of the teacher's school may start an observation.
     */
    public function create(User $user, User $teacher): bool
    {
        return $user->canObserve($teacher);
    }

    /**
     * Determine whether the user can edit the observation's details.
     */
    public function update(User $user, Observation $observation): bool
    {
        return $this->isOpenAndObservedBy($user, $observation);
    }

    /**
     * Determine whether the user can record levels and personal feedback.
     */
    public function score(User $user, Observation $observation): bool
    {
        return $this->isOpenAndObservedBy($user, $observation);
    }

    /**
     * Determine whether the user can close the observation.
     */
    public function finish(User $user, Observation $observation): bool
    {
        return $this->isOpenAndObservedBy($user, $observation);
    }

    /**
     * The observer, the teacher once finished, school admins and the organization admin may view.
     */
    public function view(User $user, Observation $observation): bool
    {
        if ($observation->observer()->is($user)) {
            return true;
        }

        if ($observation->teacher()->is($user)) {
            return $observation->status === ObservationStatus::Finished;
        }

        return $user->isSchoolAdminOf($observation->school)
            || $user->isOrganizationAdminOf($observation->school);
    }

    /**
     * Exporting the observation PDF follows the same visibility as viewing it.
     */
    public function export(User $user, Observation $observation): bool
    {
        return $this->view($user, $observation);
    }

    /**
     * Whether the observation is still in progress and the user is its observer.
     */
    private function isOpenAndObservedBy(User $user, Observation $observation): bool
    {
        return $observation->status === ObservationStatus::InProgress
            && $observation->observer()->is($user);
    }
}
