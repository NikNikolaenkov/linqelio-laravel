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

    public function create(ChannelKind $kind, ?string $name = null): Channel
    {
        $body = array_filter([
            'kind' => $kind->value,
            'name' => $name,
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
     * Hand a channel its provider credential — a bot token, or the 2FA password
     * a Telegram login is waiting on.
     *
     * Write-only by design: the value goes straight to the secret store and only
     * a reference is bound to the channel, so it can never be read back out.
     * There is no "show me the current token" call, and losing one means issuing
     * a new one with the provider.
     */
    public function setCredentials(string $id, string $token): void
    {
        $this->client->put("/channels/{$id}/credentials", ['token' => $token]);
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
