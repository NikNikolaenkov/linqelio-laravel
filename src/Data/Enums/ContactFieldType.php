<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The type of a typed contact field (ADR-0061).
 */
enum ContactFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Bool = 'bool';
    case Enum = 'enum';
    case MultiEnum = 'multi_enum';
    case Phone = 'phone';
    case Email = 'email';
    case Url = 'url';
}
