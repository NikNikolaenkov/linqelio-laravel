<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Read;

/**
 * The live count of an audience definition on a set of channels — who it would
 * reach if the campaign launched now, before any campaign exists.
 */
final readonly class AudiencePreview
{
    /**
     * @param  array<string, int>  $addressable  channel id => how many of the
     *                                           matched recipients have an address there
     */
    public function __construct(
        public AudienceCount $audience,
        public array $addressable = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $addressable = [];
        foreach (Read::objects($data, 'channels') as $channel) {
            $addressable[Read::string($channel, 'channelId')] = Read::int($channel, 'addressable');
        }

        return new self(
            audience: AudienceCount::fromArray(Read::map($data, 'audience')),
            addressable: $addressable,
        );
    }
}
