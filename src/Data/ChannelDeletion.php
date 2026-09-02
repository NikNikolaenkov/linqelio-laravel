<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * What deleting a channel actually removed.
 *
 * Reported for the same reason {@see ErasureResult} is: "the id named nothing"
 * and "it took a channel and 412 messages with it" are different facts, and
 * once the call returns, the only places either one survives are the platform's
 * log and yours.
 *
 * Deletion is NOT disconnect. Disconnect closes the live link and keeps the
 * channel so it can be reconnected; this keeps nothing — conversations, their
 * messages, the attachments, the outbound work still queued, and the inbox the
 * channel owns on the operator desk all go with it, and none of it comes back.
 */
final readonly class ChannelDeletion
{
    /**
     * @param  bool  $deleted  true when a channel row was actually removed, false
     *                         when the id named nothing in this cabinet. BOTH are
     *                         successful: the requested state holds either way,
     *                         and this is how a caller tells a deletion from a
     *                         replay of one after a lost response.
     * @param  int  $outbox  queued outbound rows removed. Each carries its own
     *                       full copy of the envelope, so one still pending is a
     *                       message that will now never be sent — the intended
     *                       answer for a channel being retired.
     * @param  int  $media  attachments queued for removal from the object store.
     *                      Queued, not deleted inline: the store is not in the
     *                      deletion's transaction, so the keys are committed with
     *                      it and a sweep removes the bytes immediately after.
     * @param  bool  $deskInboxRemoved  true when the channel's inbox on the
     *                                  operator desk went with it. false only
     *                                  when there was none or no desk is
     *                                  configured — a desk that refused fails the
     *                                  whole call instead, so this is never a
     *                                  quiet partial success.
     */
    public function __construct(
        public bool $deleted,
        public int $conversations = 0,
        public int $messages = 0,
        public int $outbox = 0,
        public int $media = 0,
        public bool $deskInboxRemoved = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deleted: (bool) ($data['deleted'] ?? false),
            conversations: (int) ($data['conversations'] ?? 0),
            messages: (int) ($data['messages'] ?? 0),
            outbox: (int) ($data['outbox'] ?? 0),
            media: (int) ($data['media'] ?? 0),
            deskInboxRemoved: (bool) ($data['deskInboxRemoved'] ?? false),
        );
    }

    /**
     * True when the id named nothing — an already-deleted channel, or one that
     * was never here.
     *
     * NOT a failure: the delete is idempotent, so a retry after a timeout lands
     * here and the requested state still holds.
     */
    public function wasAlreadyDeleted(): bool
    {
        return ! $this->deleted;
    }

    /**
     * @return array<string, bool|int>
     */
    public function toArray(): array
    {
        return [
            'deleted' => $this->deleted,
            'conversations' => $this->conversations,
            'messages' => $this->messages,
            'outbox' => $this->outbox,
            'media' => $this->media,
            'deskInboxRemoved' => $this->deskInboxRemoved,
        ];
    }
}
