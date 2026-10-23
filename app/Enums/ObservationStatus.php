<?php

namespace App\Enums;

/**
 * Lifecycle status of a classroom observation.
 */
enum ObservationStatus: string
{
    case InProgress = 'en_curso';
    case Finished = 'finalizado';
}
