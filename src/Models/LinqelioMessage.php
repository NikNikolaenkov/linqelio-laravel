<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Linqelio\Laravel\Client\BinaryResponse;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\MessageStatus;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Message;
use Linqelio\Laravel\Events\MessageReceived;
use Linqelio\Laravel\Events\MessageStatusChanged;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * A message, mirrored locally so it can be joined, searched and reported on.
 *
 * This is a projection. The platform remains the source of truth: identity
 * resolution, delivery state and retention all happen there, and a row here can
 * be stale or absent without anything being wrong. Treat it as a cache with a
 * long memory, not as your messaging system.
 *
 * @property string $id
 * @property string $channel_id
 * @property string|null $conversation_id
 * @property string $kind
 * @property string $direction
 * @property string $type
 * @property string|null $status
 * @property string $chat_id
 * @property string $contact_ref
 * @property string|null $contact_id
 * @property array<string, mixed>|null $content
 * @property string|null $text
 * @property bool $has_media
 * @property string|null $provider_message_id
 * @property Carbon $occurred_at
 */
final class LinqelioMessage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'content' => 'array',
        'has_media' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('linqelio.projection.table', 'linqelio_messages');
    }

    /**
     * Record an inbound message from a webhook.
     *
     * The webhook carries no body, so the row starts as the envelope alone and
     * is filled in if and when the content is read. Written with updateOrCreate
     * because the platform retries deliveries: the same event arriving twice
     * must leave one row, not two.
     */
    public static function recordInbound(MessageReceived $event): self
    {
        /** @var self $model */
        $model = self::query()->updateOrCreate(
            ['id' => $event->messageId],
            [
                'channel_id' => $event->channelId,
                'kind' => $event->kind->value,
                'direction' => 'inbound',
                'type' => $event->type->value,
                'chat_id' => $event->chatId,
                'contact_ref' => $event->contactRef,
                'has_media' => $event->type->isMedia(),
                'occurred_at' => $event->occurredAt,
            ],
        );

        return $model;
    }

    /**
     * Record an outbound message the platform has accepted.
     *
     * Status is whatever the send returned — `queued`, almost always. Delivery
     * moves it on later; this row is not it.
     */
    public static function recordOutbound(Message $message, string $channelId, string $contactId, string $chatId = ''): self
    {
        /** @var self $model */
        $model = self::query()->updateOrCreate(
            ['id' => $message->id],
            [
                'channel_id' => $channelId,
                'kind' => '',
                'direction' => 'outbound',
                'type' => $message->type->value,
                'status' => $message->status?->value,
                'chat_id' => $chatId,
                'contact_ref' => $chatId,
                'contact_id' => $contactId,
                'content' => $message->content,
                'text' => $message->text(),
                'has_media' => $message->media() !== null,
                'provider_message_id' => $message->providerMessageId,
                'occurred_at' => $message->timestamp,
            ],
        );

        return $model;
    }

    /**
     * Advance an outbound row to the status the platform just reported.
     *
     * Only the status moves. A status delivery names the message and says what
     * happened to it; rewriting the rest from a payload that carries no body
     * would blank the content this projection exists to hold.
     *
     * Creates the row when it is missing, because a status can arrive for a
     * message this application never recorded — a send made from somewhere else,
     * or a projection enabled after the fact. A row saying "this failed" with no
     * body still beats no row at all.
     */
    public static function recordStatus(MessageStatusChanged $event): self
    {
        /** @var self $model */
        $model = self::query()->firstOrNew(['id' => $event->messageId]);

        $model->status = $event->status->value;
        if ($event->providerMessageId !== null) {
            $model->provider_message_id = $event->providerMessageId;
        }
        // Only when this call is creating the row: an existing projection already
        // knows the channel and the address, and a status payload is not where to
        // learn them from.
        if (! $model->exists) {
            $model->channel_id = $event->channelId;
            $model->kind = $event->kind->value;
            $model->direction = 'outbound';
            $model->type = '';
            $model->chat_id = $event->chatId;
            $model->contact_ref = $event->contactRef;
            $model->occurred_at = Carbon::instance($event->occurredAt);
        }
        $model->save();

        return $model;
    }

    /**
     * Fill in the parts a webhook could not carry, from the API.
     *
     * Kept separate from recording so the write path stays fast: the envelope
     * lands immediately, and the body is backfilled by whatever needs it.
     *
     * Deliberately not called `hydrate` — Eloquent already has a static method
     * by that name which the query builder calls to turn rows into models, and
     * shadowing it breaks every read of this table.
     */
    public function fillFromMessage(Message $message, ?string $contactId = null): self
    {
        $this->forceFill(array_filter([
            'content' => $message->content,
            'text' => $message->text(),
            'status' => $message->status?->value,
            'provider_message_id' => $message->providerMessageId,
            'contact_id' => $contactId,
        ], static fn ($v): bool => $v !== null))->save();

        return $this;
    }

    /**
     * The attachment's bytes, streamed from the platform.
     *
     * Not stored locally on purpose: copying attachments into your storage
     * duplicates the obligation to delete them, and the platform already answers
     * for who may read what.
     */
    public function media(): ?BinaryResponse
    {
        return $this->has_media ? Linqelio::media()->fetch($this->id) : null;
    }

    public function kind(): ChannelKind
    {
        return ChannelKind::tryFrom($this->kind) ?? ChannelKind::WaWeb;
    }

    public function type(): MessageType
    {
        return MessageType::tryFrom($this->type) ?? MessageType::Text;
    }

    public function status(): ?MessageStatus
    {
        return $this->status !== null ? MessageStatus::tryFrom($this->status) : null;
    }

    /** @param  Builder<self>  $query */
    public function scopeInbound(Builder $query): void
    {
        $query->where('direction', 'inbound');
    }

    /** @param  Builder<self>  $query */
    public function scopeOutbound(Builder $query): void
    {
        $query->where('direction', 'outbound');
    }

    /** @param  Builder<self>  $query */
    public function scopeForContact(Builder $query, string $contactId): void
    {
        $query->where('contact_id', $contactId);
    }

    /** @param  Builder<self>  $query */
    public function scopeForConversation(Builder $query, string $conversationId): void
    {
        $query->where('conversation_id', $conversationId)->orderBy('occurred_at');
    }

    /**
     * Rows whose body was never filled in — a webhook landed but nothing has
     * asked for the content yet.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnhydrated(Builder $query): void
    {
        $query->whereNull('content');
    }
}
