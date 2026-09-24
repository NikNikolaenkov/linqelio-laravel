<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What a breakdown report groups by.
 */
enum AnalyticsBreakdownBy: string
{
    case Channel = 'channel';
    case Agent = 'agent';
    case Origin = 'origin';
    case Policy = 'policy';
}
