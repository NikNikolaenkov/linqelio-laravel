<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * A person, spanning channels.
 *
 * A contact is not "a WhatsApp number" — it is somebody who may be reachable on
 * several channels at once, each reachability recorded as an Identity. Deciding
 * that two addresses belong to the same person is the platform's job and is
 * deliberately exact: it never guesses from a matching name or avatar.
 *
 * `version` backs optimistic concurrency on updates. Send it back on a patch and
 * a concurrent edit fails loudly with `contact.version_conflict` instead of one
 * writer silently overwriting the other.
 */
final readonly class Contact
{
    /**
     * @param  array<int, Identity>  $identities
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $custom
     */
    public function __construct(
        public string $id,
        public string $cabinetId,
        public array $identities = [],
        public array $profile = [],
        public array $custom = [],
        public ?int $version = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $identities = [];
        foreach ($data['identities'] ?? [] as $identity) {
            if (is_array($identity)) {
                $identities[] = Identity::fromArray($identity);
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            cabinetId: (string) ($data['cabinetId'] ?? ''),
            identities: $identities,
            profile: is_array($data['profile'] ?? null) ? $data['profile'] : [],
            custom: is_array($data['custom'] ?? null) ? $data['custom'] : [],
            version: isset($data['_meta']['version']) ? (int) $data['_meta']['version'] : null,
        );
    }

    public function displayName(): ?string
    {
        $name = $this->profile['displayName'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        foreach ($this->identities as $identity) {
            if ($identity->pushName !== null) {
                return $identity->pushName;
            }
        }

        return null;
    }

    /** The first identity on the given channel kind, if any. */
    public function identityOn(string $channelType): ?Identity
    {
        foreach ($this->identities as $identity) {
            if ($identity->channelType === $channelType) {
                return $identity;
            }
        }

        return null;
    }

    public function phone(): ?string
    {
        foreach ($this->identities as $identity) {
            if ($identity->phone !== null) {
                return $identity->phone;
            }
        }

        return null;
    }
}
