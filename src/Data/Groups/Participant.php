<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Groups;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\ParticipantRole;
use Linqelio\Laravel\Data\Read;

/**
 * A participant of a group conversation.
 *
 * `providerId` is the messenger's id and always set; `contactId` only when the
 * participant is also a contact of the cabinet. Remove participants by
 * `providerId` — somebody in a group need not be a contact.
 */
final readonly class Participant
{
    /**
     * @param  string|null  $lid  WhatsApp's privacy id, when the messenger hides the number
     * @param  DateTimeImmutable|null  $leftAt  set for somebody who has left
     */
    public function __construct(
        public string $providerId,
        public ?ParticipantRole $role,
        public ?string $phone = null,
        public ?string $lid = null,
        public ?DateTimeImmutable $joinedAt = null,
        public ?DateTimeImmutable $leftAt = null,
        public ?string $contactId = null,
        public ?string $name = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerId: Read::string($data, 'providerId'),
            role: ParticipantRole::tryFrom(Read::string($data, 'role')),
            phone: Read::stringOrNull($data, 'phone'),
            lid: Read::stringOrNull($data, 'lid'),
            joinedAt: Read::date($data, 'joinedAt'),
            leftAt: Read::date($data, 'leftAt'),
            contactId: Read::stringOrNull($data, 'contactId'),
            name: Read::stringOrNull($data, 'name'),
        );
    }

    public function isPresent(): bool
    {
        return $this->leftAt === null;
    }
}
