<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * A report or a report export was refused: a bad date range, or an export that
 * is not ready yet (`analytics.export_not_ready`) or whose file has expired.
 */
class AnalyticsException extends LinqelioException {}
