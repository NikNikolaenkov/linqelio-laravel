<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The channel families the platform speaks.
 *
 * They differ in more than branding — how a channel is connected, and whether it
 * can be given a credential over the API at all, follows from the kind.
 */
enum ChannelKind: string
{
    /** WhatsApp via a paired phone (QR scan, like WhatsApp Web). */
    case WaWeb = 'wa_web';

    /** WhatsApp Business Cloud API (Meta Graph). */
    case WaCloud = 'wa_cloud';

    /** A Telegram user account over MTProto — paired by QR, not by token. */
    case TgClient = 'tg_client';

    /** A Telegram bot, authenticated by its bot token. */
    case TgBot = 'tg_bot';

    /** A Viber bot, authenticated by its account token. */
    case ViberBot = 'viber_bot';

    /**
     * Whether this kind is connected by scanning a code rather than by supplying
     * a token. QR kinds cannot be provisioned unattended — a human has to pair
     * the account once.
     */
    public function isPaired(): bool
    {
        return match ($this) {
            self::WaWeb, self::TgClient => true,
            default => false,
        };
    }

    /**
     * Whether a credential can be handed to this kind through
     * `PUT /channels/{id}/credentials`.
     */
    public function acceptsToken(): bool
    {
        return ! $this->isPaired();
    }

    public function label(): string
    {
        return match ($this) {
            self::WaWeb => 'WhatsApp',
            self::WaCloud => 'WhatsApp Business',
            self::TgClient => 'Telegram (account)',
            self::TgBot => 'Telegram (bot)',
            self::ViberBot => 'Viber',
        };
    }
}
