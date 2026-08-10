<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Console;

use Illuminate\Console\Command;
use Linqelio\Laravel\Data\Enums\MessageStatus;
use Linqelio\Laravel\Exceptions\LinqelioException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Sends a real message to a real person, so it asks first.
 */
final class SendTestCommand extends Command
{
    protected $signature = 'linqelio:send
                            {contact : Contact id}
                            {text : What to send}
                            {--channel= : Pin a channel instead of letting the platform choose}';

    protected $description = 'Send a test message through Linqelio';

    public function handle(): int
    {
        $contactId = $this->stringArgument('contact');
        $text = $this->stringArgument('text');

        if (! $this->confirm(sprintf('Send "%s" to contact %s?', $text, $contactId), false)) {
            $this->line('Nothing sent.');

            return self::SUCCESS;
        }

        $channel = $this->option('channel');

        try {
            $message = Linqelio::messages()->sendText(
                contactId: $contactId,
                text: $text,
                channelId: is_string($channel) && $channel !== '' ? $channel : null,
            );
        } catch (LinqelioException $e) {
            $this->error(sprintf('[%s] %s', $e->errorCode()->value, $e->getMessage()));

            return self::FAILURE;
        }

        // "queued" is the expected answer: the API accepts the command and
        // reaches the provider afterwards. Delivery shows up on a webhook.
        $status = $message->status ?? MessageStatus::Queued;

        $this->info(sprintf('Accepted: %s (%s)', $message->id, $status->value));

        return self::SUCCESS;
    }

    /** Console input is loosely typed; these two arguments are required strings. */
    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
