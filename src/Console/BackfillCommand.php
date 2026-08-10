<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Console;

use Illuminate\Console\Command;
use Linqelio\Laravel\Data\Message;
use Linqelio\Laravel\Exceptions\LinqelioException;
use Linqelio\Laravel\Facades\Linqelio;
use Linqelio\Laravel\Models\LinqelioMessage;

/**
 * Fills the projection with history that predates the webhook.
 *
 * Installing the package on a cabinet that has been talking to people for months
 * would otherwise leave the local table starting from today. This walks the
 * conversations and pulls their feeds.
 *
 * Safe to re-run: rows are keyed by message id, so a second pass updates rather
 * than duplicates. Safe to interrupt too — it commits per page.
 */
final class BackfillCommand extends Command
{
    protected $signature = 'linqelio:backfill
                            {--channel= : Only this channel}
                            {--limit=100 : Messages per page}
                            {--max-conversations=0 : Stop after N conversations (0 = all)}';

    protected $description = 'Backfill the local message projection from Linqelio';

    public function handle(): int
    {
        if (! (bool) config('linqelio.projection.enabled', true)) {
            $this->error('The projection is disabled (linqelio.projection.enabled). Nothing to fill.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $maxConversations = max(0, (int) $this->option('max-conversations'));

        $conversations = 0;
        $messages = 0;
        $cursor = null;

        try {
            do {
                $page = Linqelio::conversations()->list(
                    channelId: $this->option('channel') !== null ? (string) $this->option('channel') : null,
                    limit: $limit,
                );

                foreach ($page['conversations'] as $conversation) {
                    $id = (string) ($conversation['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }

                    $messages += $this->backfillConversation($id, $conversation, $limit);
                    $conversations++;

                    if ($maxConversations > 0 && $conversations >= $maxConversations) {
                        break 2;
                    }
                }

                $cursor = $page['nextCursor'];
            } while ($cursor !== null);
        } catch (LinqelioException $e) {
            $this->error(sprintf('[%s] %s', $e->errorCode()->value, $e->getMessage()));

            return self::FAILURE;
        }

        $this->info(sprintf('Backfilled %d messages across %d conversations.', $messages, $conversations));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $conversation
     */
    private function backfillConversation(string $conversationId, array $conversation, int $limit): int
    {
        $chatId = (string) ($conversation['chatId'] ?? '');
        $channelId = (string) ($conversation['channelId'] ?? '');
        $contactId = isset($conversation['contactId']) ? (string) $conversation['contactId'] : null;

        $stored = 0;
        $before = null;

        do {
            $page = Linqelio::conversations()->feed($conversationId, before: $before, limit: $limit);

            foreach ($page['messages'] as $message) {
                $this->store($message, $conversationId, $channelId, $chatId, $contactId);
                $stored++;
            }

            $before = $page['nextCursor'];
        } while ($before !== null);

        return $stored;
    }

    private function store(
        Message $message,
        string $conversationId,
        string $channelId,
        string $chatId,
        ?string $contactId,
    ): void {
        LinqelioMessage::query()->updateOrCreate(
            ['id' => $message->id],
            array_filter([
                'conversation_id' => $conversationId,
                'channel_id' => $channelId,
                'chat_id' => $chatId,
                'contact_ref' => $chatId,
                'contact_id' => $contactId,
                'kind' => '',
                'direction' => $message->direction,
                'type' => $message->type->value,
                'status' => $message->status?->value,
                'content' => $message->content,
                'text' => $message->text(),
                'has_media' => $message->media() !== null,
                'provider_message_id' => $message->providerMessageId,
                'occurred_at' => $message->timestamp,
            ], static fn ($v): bool => $v !== null),
        );
    }
}
