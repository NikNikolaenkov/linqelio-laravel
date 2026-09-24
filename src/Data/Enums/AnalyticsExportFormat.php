<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The file format of an analytics export.
 */
enum AnalyticsExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
}
