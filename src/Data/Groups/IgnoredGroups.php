<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Groups;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * The groups a number is in while its group chats are switched off.
 *
 * Their traffic is dropped, not stored — this is only what the platform noticed
 * passing by, so an operator can decide whether turning groups on is worth it.
 */
final readonly class IgnoredGroups
{
    /**
     * @param  array<int, array{chatId: string, subject: ?string, firstSeenAt: ?DateTimeImmutable, lastActivityAt: ?DateTimeImmutable, messages24h: int}>  $groups
     */
    public function __construct(
        public string $channelId,
        public bool $groupsEnabled,
        public array $groups = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $groups = [];
        foreach (Read::objects($data, 'items') as $item) {
            $groups[] = [
                'chatId' => Read::string($item, 'chatId'),
                'subject' => Read::stringOrNull($item, 'subject'),
                'firstSeenAt' => Read::date($item, 'firstSeenAt'),
                'lastActivityAt' => Read::date($item, 'lastActivityAt'),
                'messages24h' => Read::int($item, 'messages24h'),
            ];
        }

        return new self(
            channelId: Read::string($data, 'channelId'),
            groupsEnabled: Read::bool($data, 'groupsEnabled'),
            groups: $groups,
        );
    }
}
