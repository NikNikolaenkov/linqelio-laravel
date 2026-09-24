<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where a consent came from. Not chosen by the caller: it is WHO recorded it —
 * an API key records `host_api`, a person in the console `manual`.
 */
enum ConsentSource: string
{
    case InboundMessage = 'inbound_message';
    case Import = 'import';
    case HostApi = 'host_api';
    case DeepLink = 'deep_link';
    case Manual = 'manual';
}
