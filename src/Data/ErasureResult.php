<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * What an erasure actually removed.
 *
 * Worth recording verbatim in your own erasure journal: "we asked and it touched
 * nothing" and "it removed a contact and redacted 412 messages" are materially
 * different answers to give a regulator, and only one of them is evidence that
 * the request was carried out.
 */
final readonly class ErasureResult
{
    public function __construct(
        public int $contacts,
        public int $identities,
        public int $conversations,
        public int $messages,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contacts: (int) ($data['contacts'] ?? 0),
            identities: (int) ($data['identities'] ?? 0),
            conversations: (int) ($data['conversations'] ?? 0),
            messages: (int) ($data['messages'] ?? 0),
        );
    }

    /**
     * True when there was nothing left to erase — an already-erased person, or
     * one who was never here.
     *
     * NOT a failure: the erase is idempotent, so a retry after a timeout lands
     * here, and the requested state still holds.
     */
    public function wasAlreadyErased(): bool
    {
        return $this->contacts === 0
            && $this->identities === 0
            && $this->conversations === 0
            && $this->messages === 0;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'contacts' => $this->contacts,
            'identities' => $this->identities,
            'conversations' => $this->conversations,
            'messages' => $this->messages,
        ];
    }
}
