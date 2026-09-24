<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * How to read an analytics value. `micros` is money in millionths.
 */
enum AnalyticsUnit: string
{
    case Count = 'count';
    case Ratio = 'ratio';
    case Seconds = 'seconds';
    case Score = 'score';
    case Micros = 'micros';
}
