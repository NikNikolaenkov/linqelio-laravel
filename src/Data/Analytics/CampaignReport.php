<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * A campaign's funnel: recipients → sent → delivered → read → replied.
 *
 * Two kinds of failure, deliberately apart: `recipientsFailed` never became a
 * message, `messagesFailed` became one the provider did not deliver. A reply
 * counts when it arrives within `replyWindowHours` of the send.
 *
 * @phpstan-type Funnel array{recipients: int, pending: int, sent: int, skipped: int, recipientsFailed: int, cancelled: int, delivered: int, read: int, messagesFailed: int, replied: int, replyWindowHours: int}
 */
final readonly class CampaignReport
{
    /**
     * @param  Funnel  $funnel
     */
    public function __construct(
        public string $campaignId,
        public string $name,
        public string $status,
        public array $funnel,
        public ?DateTimeImmutable $launchedAt = null,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $f = Read::map($data, 'funnel');

        return new self(
            campaignId: Read::string($data, 'campaignId'),
            name: Read::string($data, 'name'),
            status: Read::string($data, 'status'),
            funnel: [
                'recipients' => Read::int($f, 'recipients'),
                'pending' => Read::int($f, 'pending'),
                'sent' => Read::int($f, 'sent'),
                'skipped' => Read::int($f, 'skipped'),
                'recipientsFailed' => Read::int($f, 'recipientsFailed'),
                'cancelled' => Read::int($f, 'cancelled'),
                'delivered' => Read::int($f, 'delivered'),
                'read' => Read::int($f, 'read'),
                'messagesFailed' => Read::int($f, 'messagesFailed'),
                'replied' => Read::int($f, 'replied'),
                'replyWindowHours' => Read::int($f, 'replyWindowHours'),
            ],
            launchedAt: Read::date($data, 'launchedAt'),
            finishedAt: Read::date($data, 'finishedAt'),
        );
    }
}
