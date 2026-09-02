<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Channel;
use Linqelio\Laravel\Data\ChannelDeletion;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Exceptions\IdempotencyException;

final readonly class ChannelsResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * @return array<int, Channel>
     */
    public function list(): array
    {
        return array_map(Channel::fromArray(...), $this->client->get('/channels')->items());
    }

    /**
     * Provision a channel.
     *
     * Pass an $idempotencyKey whenever the create is worth retrying. The
     * transport already sends a generated one, which protects nothing on its
     * own: a retry mints a fresh key and provisions a second channel. And a
     * channel you did not mean to create cannot be cleaned up — the platform
     * has disconnect, not delete — so the duplicate stays in the cabinet.
     *
     * With a pinned key the retry replays: the platform answers with the
     * channel the first attempt created. Derive it from whatever names the
     * intent on your side, the way a queued send does:
     *
     *     $channel = Linqelio::channels()->create(
     *         ChannelKind::TgBot,
     *         'support',
     *         IdempotencyKey::forSubject('channel', (string) $tenant->id, 'tg-support'),
     *     );
     *
     * Reuse the SAME key on the retry. A key that already named a different
     * kind or label is refused with `idempotency.key_reused`
     * ({@see IdempotencyException}).
     *
     * @param  string|null  $name  operator-facing label shown in the admin
     *                             channel list; `label` on the wire
     * @param  string|null  $idempotencyKey  pin it to make a retry replay
     *                                       instead of provisioning again
     */
    public function create(ChannelKind $kind, ?string $name = null, ?string $idempotencyKey = null): Channel
    {
        $body = array_filter([
            'kind' => $kind->value,
            'label' => $name,
        ], static fn ($v): bool => $v !== null);

        $response = $this->client->post('/channels', $body, idempotencyKey: $idempotencyKey);

        return Channel::fromArray($response->data);
    }

    /**
     * Live connection state.
     *
     * While a paired channel is `pairing`, the response carries the `qr` to
     * render and an `expiresAt` after which it must be refreshed. For a Telegram
     * account, `passwordRequired` means the code was scanned but the account's
     * 2FA password still has to be submitted through
     * {@see self::setCredentials()}.
     *
     * @return array<string, mixed>
     */
    public function status(string $id): array
    {
        return $this->client->get("/channels/{$id}/status")->data;
    }

    /**
     * Bring a channel up. For paired kinds this starts the QR flow rather than
     * finishing it — poll {@see self::status()} until it leaves `pairing`.
     *
     * @return array<string, mixed>
     */
    public function connect(string $id): array
    {
        return $this->client->post("/channels/{$id}/connect")->data;
    }

    /**
     * Hand a channel its provider credentials — a bot token, or the 2FA password
     * a Telegram login is waiting on.
     *
     * A WhatsApp Cloud channel needs three, all per-channel so you can run your
     * own Meta app rather than share one: `$token` is the Graph access token,
     * `$appSecret` signs the inbound webhooks, `$verifyToken` is what Meta echoes
     * back during verification. Send them together — a channel with a token but
     * no app secret sends fine and rejects every message coming back.
     *
     * Both extras are refused on any other kind, rather than stored where
     * nothing would read them.
     *
     * Write-only by design: values go straight to the secret store and only
     * references are bound to the channel, so none can be read back out. There is
     * no "show me the current token" call, and losing one means issuing a new one
     * with the provider.
     */
    public function setCredentials(
        string $id,
        string $token,
        ?string $appSecret = null,
        ?string $verifyToken = null,
    ): void {
        $this->client->put("/channels/{$id}/credentials", array_filter([
            'token' => $token,
            'appSecret' => $appSecret,
            'verifyToken' => $verifyToken,
        ], static fn (?string $v): bool => $v !== null && $v !== ''));
    }

    /**
     * Non-secret provider configuration — identifiers from the provider's own
     * dashboard, not credentials.
     *
     * The mirror image of {@see self::setCredentials()}: these are readable back
     * (they come with the channel in {@see self::list()}), because "which number
     * does this channel send from?" has to be answerable.
     *
     * REPLACES the stored settings. A value left null is cleared, not kept — so
     * pass everything you want to keep, and pass nothing to clear them all.
     *
     * @return array<string, mixed> the settings now stored
     */
    public function settings(string $id, ?string $phoneNumberId = null): array
    {
        return $this->client->put("/channels/{$id}/settings", array_filter([
            'phoneNumberId' => $phoneNumberId,
        ], static fn (?string $v): bool => $v !== null && $v !== ''))->data;
    }

    /**
     * Tear the live link down: a paired WhatsApp logs its instance out, a
     * Telegram account has its session wiped, a token channel unbinds its
     * credential. The stored secret stays unrecoverable either way.
     *
     * @return array<string, mixed>
     */
    public function disconnect(string $id): array
    {
        return $this->client->post("/channels/{$id}/disconnect")->data;
    }

    /**
     * Import history and contacts from the channel's own side.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sync(string $id, array $options = []): array
    {
        return $this->client->post("/channels/{$id}/sync", $options)->data;
    }

    /**
     * Retire a channel for good, with everything it carried.
     *
     * Deliberately NOT {@see self::disconnect()}: that closes the live link and
     * keeps the channel, so it can be reconnected. This keeps nothing —
     * conversations, their messages and attachments, outbound work still queued,
     * and the channel's inbox on the operator desk go with it, and none of it
     * comes back.
     *
     * The live session is torn down FIRST, and the call fails closed with
     * `provider.unavailable` if it cannot be: after the row is gone there is no
     * handle left to log that session out with, and a WhatsApp pairing would
     * stay live with nothing on our side able to reach it. A channel that is
     * already disconnected has no session to close, so disconnect first, then
     * delete, is how to retire one whose provider is gone for good.
     *
     * Idempotent: an id that names nothing succeeds with
     * {@see ChannelDeletion::wasAlreadyDeleted()} true, so a retry after a lost
     * response is not an error.
     */
    public function delete(string $id): ChannelDeletion
    {
        return ChannelDeletion::fromArray($this->client->delete("/channels/{$id}")->data);
    }
}
