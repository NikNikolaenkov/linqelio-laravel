<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The role of the channel's account in a Telegram chat.
 */
enum ChatRole: string
{
    /** The chat's creator. */
    case Owner = 'owner';

    /** An administrator. */
    case Admin = 'admin';
}
