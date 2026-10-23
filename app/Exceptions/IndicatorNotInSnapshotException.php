<?php

namespace App\Exceptions;

use App\Models\Observation;
use LogicException;

/**
 * Raised when a score references an indicator absent from, or incomplete in, the observation's rubric snapshot.
 */
class IndicatorNotInSnapshotException extends LogicException
{
    public static function for(Observation $observation, int $indicatorId): self
    {
        return new self("Indicator [{$indicatorId}] is not part of the rubric snapshot of observation [{$observation->getKey()}].");
    }

    /**
     * @param  list<string>  $missingKeys
     */
    public static function incomplete(Observation $observation, int $indicatorId, array $missingKeys): self
    {
        $keys = implode(', ', $missingKeys);

        return new self("Indicator [{$indicatorId}] in the rubric snapshot of observation [{$observation->getKey()}] is missing [{$keys}].");
    }
}
