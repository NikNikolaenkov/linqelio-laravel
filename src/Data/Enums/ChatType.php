<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The kind of a Telegram chat the channel's account administers.
 */
enum ChatType: string
{
    /** A basic group — addressed as `chat:<id>`. */
    case Group = 'group';

    /** A supergroup — addressed as `channel:<id>`. */
    case Supergroup = 'supergroup';

    /** A broadcast channel — addressed as `channel:<id>`. */
    case Channel = 'channel';
}
