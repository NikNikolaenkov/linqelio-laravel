<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Policy\SendTemplate;
use Linqelio\Laravel\Data\Read;

/**
 * The body of a send, shared by the four operations that take one: a send and
 * its policy check, to a contact or into a conversation. One builder, so a
 * check can never be asked about a different message than the send carries.
 *
 * @internal
 */
final class SendBody
{
    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, string>|null  $acknowledgedWarnings  null = an unconfirmed send
     * @return array<string, mixed>
     */
    public static function build(
        MessageType $type,
        array $content,
        ?string $channelId = null,
        ?string $replyTo = null,
        ?array $acknowledgedWarnings = null,
        ?SendTemplate $template = null,
    ): array {
        return Read::compact([
            'type' => $type->value,
            // `content` is a JSON object in the contract; an empty PHP array
            // would encode as `[]`, which a template send (all in `template`)
            // would otherwise put on the wire.
            'content' => $content === [] ? new \stdClass : $content,
            'channelId' => $channelId,
            'replyTo' => $replyTo,
            // An EMPTY list is meaningful — "I confirm there are no warnings" —
            // and is sent as such; only null means an unconfirmed send.
            'acknowledgedWarnings' => $acknowledgedWarnings === null ? null : array_values($acknowledgedWarnings),
            'template' => $template?->toArray(),
        ]);
    }
}
