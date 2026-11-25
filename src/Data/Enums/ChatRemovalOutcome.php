<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What happened in one chat when one person was removed from the channel's chats.
 */
enum ChatRemovalOutcome: string
{
    case Removed = 'removed';
    case NotMember = 'not_member';
    case NotOnTelegram = 'not_on_telegram';

    /** The removal failed; `reason` says why. */
    case Failed = 'failed';

    /** Telegram asked the account to wait before this chat: repeat the call. */
    case RateLimited = 'rate_limited';

    /** The call's time ran out before this chat: repeat the call. */
    case NotProcessed = 'not_processed';

    /** Whether the chat still has to be handled by a repeated call. */
    public function isPending(): bool
    {
        return $this === self::RateLimited || $this === self::NotProcessed;
    }
}
