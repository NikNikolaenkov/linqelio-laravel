<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Which way a message went.
 */
enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
