<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Channel;
use Linqelio\Laravel\Data\Enums\ChannelKind;

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
     * @param  string|null  $name  operator-facing label shown in the admin
     *                             channel list; `label` on the wire
     */
    public function create(ChannelKind $kind, ?string $name = null): Channel
    {
        $body = array_filter([
            'kind' => $kind->value,
            'label' => $name,
        ], static fn ($v): bool => $v !== null);

        return Channel::fromArray($this->client->post('/channels', $body)->data);
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
}
