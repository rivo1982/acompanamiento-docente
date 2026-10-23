<?php

namespace App\Enums;

/**
 * Level a pedagogical support tip helps the teacher reach.
 */
enum TeachingTipTarget: string
{
    case Achieved = 'logrado';
    case Outstanding = 'destacado';
}
