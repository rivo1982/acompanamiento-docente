<?php

namespace App\Exceptions;

use App\Models\Observation;
use LogicException;

/**
 * Raised when trying to finish an observation that was already closed.
 */
class ObservationAlreadyFinishedException extends LogicException
{
    public static function for(Observation $observation): self
    {
        return new self("Observation [{$observation->getKey()}] is already finished.");
    }
}
