<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Which report an analytics export renders.
 */
enum AnalyticsReportKind: string
{
    case Overview = 'overview';
    case Timeseries = 'timeseries';
    case Breakdown = 'breakdown';
    case Campaign = 'campaign';
}
