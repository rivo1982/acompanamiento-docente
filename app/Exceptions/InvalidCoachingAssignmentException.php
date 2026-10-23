<?php

namespace App\Exceptions;

use App\Models\School;
use App\Models\User;
use LogicException;

/**
 * Raised when an assignment pairs an unflagged observer, the same person, or staff from another school.
 */
class InvalidCoachingAssignmentException extends LogicException
{
    public static function for(School $school, User $observer, User $teacher): self
    {
        return new self("User [{$observer->getKey()}] cannot be assigned to observe user [{$teacher->getKey()}] in school [{$school->getKey()}].");
    }
}
