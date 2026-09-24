<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * An alert or an alert subscription could not be found or changed — including
 * `alert.subscription_conflict`: one subscription per target and delivery channel.
 */
class AlertException extends LinqelioException {}
