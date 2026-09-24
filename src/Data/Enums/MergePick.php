<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Which side of a merge a field value is taken from.
 */
enum MergePick: string
{
    case Survivor = 'survivor';
    case Merged = 'merged';
}
