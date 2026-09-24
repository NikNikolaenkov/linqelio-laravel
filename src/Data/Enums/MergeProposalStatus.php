<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What became of a merge proposal.
 */
enum MergeProposalStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Merged = 'merged';
}
