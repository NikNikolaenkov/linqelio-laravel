<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What a CSV column of a contact import maps to.
 */
enum ContactImportTarget: string
{
    case Identity = 'identity';
    case HostRef = 'hostRef';
    case Name = 'name';
    case Field = 'field';
    case Tags = 'tags';
    case Consent = 'consent';
    case Ignore = 'ignore';
}
