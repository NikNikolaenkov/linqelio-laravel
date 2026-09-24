<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where an alert subscription delivers.
 */
enum AlertDeliveryChannel: string
{
    case Console = 'console';
    case Email = 'email';
    case Webhook = 'webhook';
    case Bitrix24 = 'bitrix24';
}
