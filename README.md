# Linqelio for Laravel

One API for WhatsApp, Telegram and Viber conversations.

[![Tests](https://github.com/NikNikolaenkov/linqelio-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/NikNikolaenkov/linqelio-laravel/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/linqelio/linqelio-laravel.svg)](https://packagist.org/packages/linqelio/linqelio-laravel)
[![License](https://img.shields.io/packagist/l/linqelio/linqelio-laravel.svg)](LICENSE)

```php
$customer->sendMessage('Your order has shipped');
```

## Requirements

PHP 8.2+ · Laravel 12 · a Linqelio installation with a client API key.

Laravel 11 is not supported: that line is past its security-support window, so
every release in it carries unpatched advisories and Composer refuses to install
one under its default policy.

## Installation

```bash
composer require linqelio/linqelio-laravel
php artisan vendor:publish --tag=linqelio-config
php artisan migrate
```

```dotenv
LINQELIO_URL=https://your-linqelio-host/v1
LINQELIO_KEY=<cabinetId>.<keyId>.<secret>
LINQELIO_WEBHOOK_SECRET=...
```

There is no default URL on purpose. A fallback would quietly send your
customers' messages to somebody else's host, and that failure is silent — so an
unset `LINQELIO_URL` raises an exception instead.

Check it works:

```bash
php artisan linqelio:channels
```

### Serving several cabinets

`LINQELIO_KEY` binds the package to one cabinet. An application that serves more
than one asks for the key it needs at the call site:

```php
Linqelio::forKey($tenant->linqelio_key)->messages()->sendText($contactId, 'hello');
```

Do not rebind the container to switch cabinets. The client is a singleton, and
under Octane or a queue worker it outlives the request that resolved it — the
next request would inherit whatever key was set last. `forKey()` returns a
separate instance and leaves the shared one alone.

## Sending

```php
use Linqelio\Laravel\Facades\Linqelio;
use Linqelio\Laravel\Data\Enums\MessageType;

Linqelio::messages()->sendText($contactId, 'Your order has shipped');
```

A send returns a **queued** message. The API accepts the command and reaches the
provider afterwards, so success means "handed over", not "delivered" — delivery
arrives later, on a webhook.

The channel is chosen for you: the contact's most recent conversation, falling
back to one matching their identities. Pin it when it matters:

```php
Linqelio::messages()->sendText($contactId, 'Hi', channelId: $channelId);
```

### Idempotency

Every unsafe command carries an `Idempotency-Key`. One is generated for you, so
a forgotten key cannot turn a timeout into a duplicate message on somebody's
phone. Supply your own when the command has a natural identity:

```php
Linqelio::messages()->sendText(
    contactId: $contactId,
    text: 'Your order has shipped',
    idempotencyKey: "order-{$order->id}-shipped",
);
```

Then even a redeploy or a replay from another process cannot send it twice.

Prefer the queue for anything triggered by a request — delivery should not be
able to slow a checkout down or fail it:

```php
use Linqelio\Laravel\Jobs\SendMessage;

SendMessage::dispatch($contactId, MessageType::Text, ['text' => 'Thanks!']);
```

Queued sends derive their key from the job, so a retry repeats the same key
rather than sending a second message.

### Attachments

Outbound is pull-based — the provider fetches the file — so upload first:

```php
$media = Linqelio::media()->uploadFile($request->file('invoice'));

Linqelio::messages()->sendMedia(
    contactId: $contactId,
    type: MessageType::Document,
    media: $media,
    caption: 'Your invoice',
);
```

Reading an inbound attachment goes through the platform, not the object store:

```php
return Linqelio::media()->fetch($messageId)->toResponse();
```

The URL stored on an old message is a presigned link that has long expired. The
fetch above streams the bytes under your key, and sets `nosniff` — the content
type comes from whoever sent the file, so serving it unguarded on your own
origin is how an attachment claiming to be HTML ends up executing there.

## Receiving

Register `https://your-app.test/linqelio/webhook` with Linqelio, put the same
secret in `LINQELIO_WEBHOOK_SECRET`, and listen:

```php
use Linqelio\Laravel\Events\MessageReceived;

class NotifyOperator
{
    public function handle(MessageReceived $event): void
    {
        $message = $event->message();   // fetched only if you ask

        Operator::notify($message?->text());
    }
}
```

The webhook carries routing identifiers only — never the message body. That is
deliberate: a body can contain anything a customer typed or attached, and
shipping it to every registered endpoint would spread it further than the API's
own access rules reach.

So `$event->message()` costs a call, and `$event->contact()` another. Both are
memoised. A listener that only needs `$event->messageId` to queue work pays for
neither.

Deliveries are verified (HMAC-SHA256 over the raw body), rejected if older than
the tolerance, processed once, and handled on a queue.

Newer platforms also send `X-Linqelio-Signature-V2`, which signs the send
timestamp and delivery id alongside the body. Where the older signature covers
the body alone — leaving those two headers unauthenticated, and so unusable for
telling a retry from a replay — v2 makes both trustworthy. The middleware uses it
when it is there and falls back when it is not, so there is nothing to switch on;
the only visible difference is that `webhooks.tolerance` can stay tight, because
freshness is then measured per attempt rather than from the event.

### Managing subscriptions

Registering is usually a one-off, but the rest of the lifecycle is not — an
endpoint breaks, a deployment moves, a tenant leaves:

```php
Linqelio::webhooks()->register('https://your-app.test/linqelio/webhook',
    ['message.inbound'], 'secret://webhooks/your-app');

Linqelio::webhooks()->disable($id);   // stop delivering, keep the subscription
Linqelio::webhooks()->enable($id);
Linqelio::webhooks()->delete($id);    // unsubscribe for good
```

Reach for `disable()` rather than `delete()` when an endpoint is merely broken:
deleting takes the signing key with it, so coming back means handing out a new
one. `delete()` is idempotent — deleting an id that is already gone succeeds, so
a retry after a lost response is not an error.

## Erasing a person

When someone asks to be deleted, one call removes them:

```php
$result = Linqelio::contacts()->erase($contactId);

$result->toArray();  // ['contacts' => 1, 'identities' => 3, ...] — record this
```

This is deliberately not a delete of the contact record. Messages carry the
person's number in their own columns and have no link back to a contact to
cascade through, so the platform redacts them instead — bodies, metadata, chat
ids, contact references — in one transaction. The counts come back so you can
enter them in your own erasure journal: "we asked and it touched nothing" and
"it redacted 412 messages" are different things to be able to show later.

Irreversible, and idempotent: erasing someone already erased returns zero counts
(`$result->wasAlreadyErased()`) rather than failing, so a retry after a timeout
is safe.

**It cannot reach your copies.** The local projection below lives in *your*
database, and so does anything you derived from it:

```php
LinqelioMessage::where('contact_id', $contactId)->delete();
```

## Local projection

Messages are mirrored into `linqelio_messages` so you can join, search and
report on them without a network call:

```php
use Linqelio\Laravel\Models\LinqelioMessage;

LinqelioMessage::query()
    ->forContact($contactId)
    ->inbound()
    ->where('text', 'like', "%invoice%")
    ->latest('occurred_at')
    ->get();
```

It is a projection, not a source of truth. Rows are keyed by the platform's
message id — a ULID, stable forever — and never by chat id: an address can be
re-keyed underneath you when the platform learns that a phone number and a
messenger account are the same person, and a table keyed on it silently splits
in two.

Attachment bytes are not copied; `$message->media()` streams them on demand.

Backfill history for a cabinet that predates the install:

```bash
php artisan linqelio:backfill
```

Turn the whole thing off with `LINQELIO_PROJECTION=false` if events are enough.

## Attaching to your models

```php
use Linqelio\Laravel\Concerns\HasLinqelioContact;

class Customer extends Model
{
    use HasLinqelioContact;   // needs a nullable linqelio_contact_id column
}

$customer->linkLinqelioContact(ChannelKind::WaWeb, phone: '+380501082555');
$customer->queueMessage('Thanks for your order');
$customer->linqelioMessages()->latest('occurred_at')->get();
```

Contacts themselves are not mirrored. A contact is a *person* who may be
reachable on several channels, and deciding that two addresses are the same
person is the platform's job — it never guesses from a matching name. A local
copy would diverge exactly there, and quietly.

## Errors

Failures raise typed exceptions carrying a stable code:

```php
use Linqelio\Laravel\Exceptions\{PolicyException, ChannelException, LinqelioException};

try {
    Linqelio::messages()->sendText($contactId, $text);
} catch (PolicyException $e) {
    // rate limited or over quota
    retryIn($e->retryAfter() ?? 60);
} catch (ChannelException $e) {
    // not connected, or the contact is not reachable there
    report($e->errorCode()->value);
} catch (LinqelioException $e) {
    report($e);
}
```

Switch on `$e->errorCode()`, not on HTTP status or message text. The registry is
additive — codes are never reassigned — so matching one is safe across versions,
and a code newer than this package still lands in the right exception family.

## Embedded widget

```php
// AppServiceProvider
EmbedTokenController::resolveContactUsing(
    fn (Request $request) => $request->user()?->linqelio_contact_id
);
```

Set `LINQELIO_EMBED_ENABLED=true` and the widget can fetch a short-lived token
from `/linqelio/embed-token`. It is never rendered into HTML: the token is scoped
to one person's conversation, and HTML settles into page caches, browser history
and CDN logs.

## Testing

```php
Http::fake(['*/contacts/*/messages' => Http::response(['id' => '01ABC', 'type' => 'text'], 202)]);
```

The package uses Laravel's HTTP client throughout, so `Http::fake()` covers it.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security issues: [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
