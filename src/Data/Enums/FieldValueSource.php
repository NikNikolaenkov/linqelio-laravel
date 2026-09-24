<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Who wrote a typed field value. Authority runs `human > host > platform > ai`:
 * a write never overwrites a value from a more authoritative writer.
 */
enum FieldValueSource: string
{
    case Human = 'human';
    case Host = 'host';
    case Platform = 'platform';
    case Ai = 'ai';
}
