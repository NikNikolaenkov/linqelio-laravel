<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A local, queryable copy of the conversation history.
 *
 * The primary key is the platform's message id — a ULID, minted once and stable
 * forever. Deliberately NOT the chat id: a chat id identifies an address, and an
 * address can be re-keyed underneath you. Send to a Telegram account by phone and
 * the platform will move that conversation onto the numeric user id once the
 * messenger resolves it. A table keyed on the address silently becomes two.
 *
 * Attachment bytes are not stored. `has_media` says one exists; the content is
 * streamed from the API by message id when something actually needs it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            // The platform's ULID, not an auto-increment: it is what a webhook
            // hands us and what makes a re-delivery idempotent.
            $table->string('id', 40)->primary();

            $table->string('cabinet_id', 64)->nullable();
            $table->string('channel_id', 64)->index();
            $table->string('conversation_id', 64)->nullable()->index();

            $table->string('kind', 32);
            $table->string('direction', 16);
            $table->string('type', 32);
            $table->string('status', 32)->nullable();

            // How the channel addresses the other party. An attribute, not a key
            // — see the note above about re-keying.
            $table->string('chat_id', 191)->index();
            $table->string('contact_ref', 191)->index();

            // Filled in when the contact has been resolved; null until then, so
            // a message is never held up waiting for a lookup.
            $table->string('contact_id', 64)->nullable()->index();

            // The full content payload, plus the text lifted out of it so the
            // common case — searching what was said — does not need JSON
            // functions and works the same on every supported database.
            $table->json('content')->nullable();
            $table->text('text')->nullable();

            $table->boolean('has_media')->default(false);
            $table->string('provider_message_id', 191)->nullable()->index();

            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // The two reads that matter: a thread in order, and a contact's
            // history across channels.
            $table->index(['conversation_id', 'occurred_at']);
            $table->index(['contact_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('linqelio.projection.table', 'linqelio_messages');
    }
};
