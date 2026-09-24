<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Why a campaign is paused: by somebody, or because its organisation was
 * suspended (it resumes by itself when that is lifted).
 */
enum CampaignPauseReason: string
{
    case Manual = 'manual';
    case Suspended = 'suspended';
}
