<?php

namespace App\Policies;

use App\Models\Indicator;
use App\Models\User;

/**
 * The platform catalog is readable by every authenticated user and edited only by seeding.
 */
class IndicatorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Indicator $model): bool
    {
        return true;
    }
}
