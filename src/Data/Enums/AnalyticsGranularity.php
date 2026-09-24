<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * A time series' bucket, in the cabinet's time zone.
 */
enum AnalyticsGranularity: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
}
