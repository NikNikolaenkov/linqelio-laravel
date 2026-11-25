<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What happened to one person of an add or remove call on a Telegram chat.
 */
enum ChatMemberOutcome: string
{
    /** Added to the chat directly. */
    case Added = 'added';

    /** Already in the chat; nothing was done. */
    case AlreadyMember = 'already_member';

    /**
     * Telegram refused a direct add (privacy settings), so a personal invite link
     * was sent to the person in a message. Its delivery is that message's own
     * status (`inviteMessageId`), not this outcome.
     */
    case InviteQueued = 'invite_queued';

    /**
     * An invite link was created but the message carrying it was not accepted
     * for sending; `reason` says why. Hand `inviteLink` to the person another way.
     */
    case InviteNotSent = 'invite_not_sent';

    /** Removed from the chat (a kick: they may rejoin). */
    case Removed = 'removed';

    /** Was not in the chat. */
    case NotMember = 'not_member';

    /** The phone number has no Telegram account. */
    case NotOnTelegram = 'not_on_telegram';

    /** It failed for this person; `reason` says why. */
    case Failed = 'failed';
}
