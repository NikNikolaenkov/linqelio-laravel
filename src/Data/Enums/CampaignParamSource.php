<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where one template parameter's value comes from, per recipient.
 */
enum CampaignParamSource: string
{
    case Literal = 'literal';
    case ContactName = 'contact_name';
    case ContactField = 'contact_field';
}
