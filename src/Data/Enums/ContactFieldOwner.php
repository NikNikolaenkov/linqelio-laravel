<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Who a typed contact field belongs to — who is expected to write it.
 */
enum ContactFieldOwner: string
{
    case Platform = 'platform';
    case Host = 'host';
    case Ai = 'ai';
}
