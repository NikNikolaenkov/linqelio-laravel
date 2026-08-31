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
     * @param  array<int, HostRef>  $hostRefs
     */
    public function __construct(
        public string $id,
        public string $cabinetId,
        public array $identities = [],
        public array $profile = [],
        public array $custom = [],
        public ?int $version = null,
        public array $hostRefs = [],
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

        $hostRefs = [];
        foreach ($data['hostRefs'] ?? [] as $ref) {
            if (is_array($ref)) {
                $hostRefs[] = HostRef::fromArray($ref);
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            cabinetId: (string) ($data['cabinetId'] ?? ''),
            identities: $identities,
            profile: is_array($data['profile'] ?? null) ? $data['profile'] : [],
            custom: is_array($data['custom'] ?? null) ? $data['custom'] : [],
            version: isset($data['_meta']['version']) ? (int) $data['_meta']['version'] : null,
            hostRefs: $hostRefs,
        );
    }

    /**
     * The id this contact is linked to in one of your systems, if any.
     *
     * `hostRefs` were writable through `update()` and unreadable here, so the one
     * question the link exists to answer — "is this person already my customer
     * 4711?" — could not be asked of the object you got back.
     */
    public function hostRef(string $system): ?string
    {
        foreach ($this->hostRefs as $ref) {
            if ($ref->system === $system) {
                return $ref->externalId;
            }
        }

        return null;
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

        // A handle is a poor name and a good deal better than none. Telegram
        // accounts often carry nothing else, and stopping at push name left them
        // reading as anonymous in every list built from this method — while the
        // platform, whose own fallback goes on to the username, showed a name.
        foreach ($this->identities as $identity) {
            if ($identity->username !== null) {
                return $identity->username;
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
