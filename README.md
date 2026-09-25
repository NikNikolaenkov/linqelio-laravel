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

The key is fixed once per call, before the first attempt, so the client's own
retries (on 429 and 5xx) repeat it rather than doing the work again.

The same holds for the creating operations the platform replays (ADR-0103) —
`campaigns()->create()` / `launch()`, `groups()->create()` and the participant
changes, `alerts()->subscribe()`, `contactImports()->upload()` / `start()`,
`contactExports()->create()`, `analytics()->export()`,
`contacts()->fillAiProfile()` / `invite()`, `webhooks()->register()`. Each takes
an optional `idempotencyKey:`; a retry with the same key and the same request
gets the first answer back and nothing is done twice. The same key with a
DIFFERENT request is refused (`idempotency.key_reused`), and a retry while the
first request is still running answers `idempotency.in_progress` — wait and
send it again unchanged:

```php
use Linqelio\Laravel\Exceptions\IdempotencyException;

try {
    Linqelio::campaigns()->launch($campaignId, idempotencyKey: "launch-{$campaignId}");
} catch (IdempotencyException $e) {
    if ($e->isInProgress()) {
        $this->release($e->retryAfter() ?? 1);   // the first launch is still running
    }
}
```

A replayed answer is marked `Idempotent-Replayed: true`; on the raw client
`Response::replayed()` reads it. The typed wrappers return the same object
either way — a replay IS the first result.

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

### Check → confirm → send

Send policy can hold a message back — a rate limit, a frequency rule, business
hours, missing consent — or let it through with a warning somebody should see.
Ask it first; the check sends nothing and consumes nothing, so it is safe to
call while an operator is still typing:

```php
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Exceptions\PolicyException;

$content = ['text' => 'Your order has shipped'];

// 1. Check.
$check = Linqelio::messages()->check($contactId, MessageType::Text, $content);

if (! $check->sendable) {
    // deny or defer: $check->blockers() say why, $check->retryAt when
    return back()->withErrors(array_map(fn ($f) => $f->message(), $check->blockers()));
}

// 2. Confirm: show $check->warnings() and let somebody agree.
if ($check->needsConfirmation() && ! $request->boolean('confirmed')) {
    return view('confirm-send', ['warnings' => $check->warnings()]);
}

// 3. Send, acknowledging exactly the warnings that were shown.
try {
    Linqelio::messages()->send($contactId, MessageType::Text, $content,
        acknowledgedWarnings: $check->warningKeys);
} catch (PolicyException $e) {
    if ($e->needsConfirmation()) {
        // A warning appeared between check and send. Nothing was sent;
        // $e->findings() are the new ones — show them and ask again.
    }
}
```

`acknowledgedWarnings` makes it a *confirmed* send: it goes out only if every
warning policy raises at that moment was acknowledged. An empty array confirms
"no warnings". Leave the argument out for an unconfirmed send, where warnings
never block and come back on the result's `policyWarnings`.

A finding's `code` is a message key to localise with its `params`; `message()`
falls back to the platform's English `detail` for a code you do not know. A
verdict newer than this package reads as `deny`.

WhatsApp Business templates — the one thing a `wa_cloud` channel may send
outside the 24-hour window — go through the same path:

```php
$templates = Linqelio::channels()->templates($channelId);   // as of the last sync
$template = $templates->find('order_update', 'uk');

Linqelio::messages()->sendTemplate($contactId, $template->toSend(['A-17', 'Olena']));

Linqelio::channels()->syncTemplates($channelId);   // after a template was approved
```

The template alone carries the message: no `content` goes on the wire. The same
works into a `wa_cloud` conversation — `conversations()->send($id,
MessageType::Template, [], template: $send)` — where the platform renders it
from the channel's synced template.

### Consent

Consent is per channel. While a cabinet enforces it, a send on a channel
without an active consent is refused with `policy.consent_missing`. Record it
from the place the person actually said yes:

```php
Linqelio::contacts()->grantConsent($contactId, $channelId, evidence: "crm-form-{$form->id}");
Linqelio::contacts()->revokeConsent($contactId, $channelId);

foreach (Linqelio::contacts()->consents($contactId) as $consent) {
    $consent->status;   // granted | revoked | missing
    $consent->source;   // host_api for anything recorded through this package
}
```

The source is not an argument: the platform records who called, and an API key
is `host_api`. `evidence` is a reference (a form id, a CRM record), never
personal data. Both calls are idempotent — `changed` is false on a repeat — but
a grant lifts an earlier revocation, so call it only when the person said yes
again.

### Typed contact fields

```php
Linqelio::contacts()->fieldDefinitions();   // the cabinet's schema: key, type, options

$result = Linqelio::contacts()->setFields($contactId, ['tier' => 'gold', 'old_note' => null]);
$result->applied;   // ['tier']
$result->cleared;   // ['old_note']
$result->kept;      // fields a PERSON set, which a host write does not overwrite

$values = Linqelio::contacts()->fields($contactId);
$values->get('tier');
$values->origin('tier')->source;   // host
```

Writes are recorded as `host`. Authority runs `human > host > platform > ai`: a
value an operator corrected by hand is kept, reported in `kept`, and not an
error. An invalid value fails the whole write with `contact.field_invalid`;
`ContactException::errors()` names each field and why.

The AI questionnaire fills fields too, and never over anybody else's value:

```php
Linqelio::contacts()->aiProfile($contactId);       // fields, summary, last run
Linqelio::contacts()->fillAiProfile($contactId);   // queue a run now (idempotent)
```

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

### Knowing whether a send worked

`send()` returns when the platform accepts the command, not when the provider is
reached — so a 202 is "handed over", never "delivered". Subscribe to
`message.status` and the outcome comes to you:

```php
Linqelio::webhooks()->register('https://your-app.test/linqelio/webhook',
    ['message.inbound', 'message.status']);
```

```php
public function handle(MessageStatusChanged $event): void
{
    if ($event->hasFailed()) {
        // $event->reason says why: an unaddressable recipient, an unsupported
        // type. It will not be retried.
        Log::warning('send failed', ['id' => $event->messageId, 'why' => $event->reason]);
    }
}
```

Every transition is delivered — `sent`, `delivered`, `read`, `failed`. On a busy
cabinet read receipts are the bulk of that, so name only the ones you want in
`eventTypes`.

For one message rather than a stream, `Linqelio::messages()->find($id)` reads its
current status directly; `failureReason()` on the result answers "why" for a
failed one. At volume prefer the event — one call per message does not scale.

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
$registered = Linqelio::webhooks()->register('https://your-app.test/linqelio/webhook',
    ['message.inbound']);

$registered->id();      // the subscription
$registered->secret;    // the signing key — SHOWN ONCE, see below

Linqelio::webhooks()->disable($id);   // stop delivering, keep the subscription
Linqelio::webhooks()->enable($id);
Linqelio::webhooks()->delete($id);    // unsubscribe for good
```

Reach for `disable()` rather than `delete()` when an endpoint is merely broken:
deleting takes the signing key with it, so coming back means handing out a new
one. `delete()` is idempotent — deleting an id that is already gone succeeds, so
a retry after a lost response is not an error.

#### The signing key

Every subscription is signed, and you choose where the key comes from:

```php
// The platform mints one and hands it back — the only time it is ever returned.
$registered = Linqelio::webhooks()->register($url, ['message.inbound']);
$registered->secret;   // put this in webhooks.secret before it goes out of scope

// Or sign with a key your deployment already holds. It goes to the platform's
// secret store; nothing reads it back.
Linqelio::webhooks()->register($url, ['message.inbound'],
    secret: config('linqelio.webhooks.secret'));

// Or point at one already in that store, if you administer it yourself.
Linqelio::webhooks()->register($url, ['message.inbound'],
    secretRef: 'secret://webhooks/your-app');
```

`$registered->secret` is null in the last two cases — you already have the key —
and set only when the platform generated it. It is not recoverable: no read
returns it, so if it is lost the way back is `delete()` and register again.

`secret` is the key; `secretRef` is a `secret://` address in the platform's own
store. Passing a `secret://…` string as `secret` is refused rather than sent,
because the platform would store and sign with it literally, and every delivery
would then fail verification here for a reason nothing in the logs explains.

## Conversations and groups

A group is a conversation, not a contact, so a contact send cannot reach it:

```php
Linqelio::conversations()->send($conversationId, MessageType::Text, ['text' => 'Hi all']);
Linqelio::conversations()->check($conversationId, MessageType::Text, ['text' => 'Hi all']);
Linqelio::conversations()->participants($conversationId);   // role, joinedAt, leftAt

$change = Linqelio::groups()->create($channelId, 'Project team', [$contactA, $contactB]);
Linqelio::groups()->addParticipants($change->conversationId, [$contactC]);
Linqelio::groups()->removeParticipants($change->conversationId, [$providerId]);
Linqelio::groups()->rename($change->conversationId, 'Project team (2026)');
Linqelio::groups()->leave($change->conversationId);
Linqelio::groups()->ignored($channelId);   // groups a number is in while groups are off
```

Group changes happen on the messenger and can be partial: check
`$change->failed` (who, and why) rather than assuming everyone is in.

## Scheduled sends

One message to one contact, held by the platform until its moment — it survives
your deploys and queue outages, and an operator can see and cancel it:

```php
$scheduled = Linqelio::scheduledSends()->create($contactId, $booking->remindAt,
    MessageType::Text, ['text' => 'See you tomorrow'],
    idempotencyKey: "booking-{$booking->id}-reminder");

Linqelio::scheduledSends()->update($scheduled->id, sendAt: $newTime);
Linqelio::scheduledSends()->cancel($scheduled->id);
Linqelio::scheduledSends()->list(contactId: $contactId);
```

## Campaigns

One message to an audience, paced by send policy — a limit reschedules a
recipient instead of failing it, and a recipient without consent is skipped.
Launch requires a dry run newer than the draft's last change:

```php
use Linqelio\Laravel\Data\Campaigns\{CampaignAudience, CampaignContent, CampaignInput};

$draft = Linqelio::campaigns()->create(new CampaignInput(
    name: 'Autumn sale',
    channelIds: [$channelId],
    content: CampaignContent::text('20% off this week'),
    audience: CampaignAudience::tagged(['vip']),
));

$run = Linqelio::campaigns()->dryRun($draft->id);
// $run->audience->matched, $run->blocked (who is skipped, why), $run->estimate,
// $run->problems (why it would not launch)

if ($run->launchable) {
    $launch = Linqelio::campaigns()->launch($draft->id);   // snapshot: $launch->recipients
}

Linqelio::campaigns()->pause($draft->id);
Linqelio::campaigns()->resume($draft->id);
Linqelio::campaigns()->recipients($draft->id, CampaignRecipientState::Failed);
```

`CampaignAudience::everyone()` is the only way to address every reachable
contact — an empty audience is nobody. Before a draft exists,
`previewAudience($channelIds, $audience)` gives the live count. Template
campaigns take per-recipient parameters:
`CampaignTemplateParam::contactName('friend')`, `::contactField('city')`,
`::literal('20%')`.

## Channel health and alerts

```php
Linqelio::health()->list();                        // score, rating, explanation
Linqelio::health()->history($channelId, now()->subWeek());

$page = Linqelio::alerts()->list(state: AlertState::Active);   // open + acknowledged
Linqelio::alerts()->list(state: AlertState::Active, cursor: $page['nextCursor']);   // next page
Linqelio::alerts()->list(AlertStatus::Resolved, before: now()->subDay());
Linqelio::alerts()->acknowledge($alertId);
Linqelio::alerts()->resolve($alertId);
```

Route alerts to the webhook your other events already use:

```php
Linqelio::alerts()->subscribe(AlertDeliveryChannel::Webhook,
    webhookId: $webhookId, minSeverity: AlertSeverity::Warning);
```

A `rating` newer than this package reads as `unknown`, and an alert of a type it
does not know keeps its raw `typeValue` with `type` null — never a crash.

## Contact import and export

```php
$job = Linqelio::contactImports()->upload($csv, 'customers.csv');

$mapping = new ImportMapping(
    ImportColumn::identity($job->columnOf('phone'), ChannelKind::WaWeb),
    ImportColumn::hostRef($job->columnOf('id'), 'crm'),
    ImportColumn::consent($job->columnOf('opt_in'), $channelId),
);

Linqelio::contactImports()->preview($job->id, $mapping);   // nothing written
Linqelio::contactImports()->start($job->id, $mapping);
// poll find($job->id) until $job->status->isFinished(), then:
Linqelio::contactImports()->report($job->id)->save(storage_path('import-report.csv'));

$export = Linqelio::contactExports()->create(new ContactExportFilter(tags: ['vip']));
// poll find($export->id) until $export->status->isReady(), then:
return Linqelio::contactExports()->download($export->id)->toResponse(download: true);
```

An import needs a `consent` column: it is how a cabinet brings in people it may
message, and it has to say on whose word.

## Analytics

```php
$overview = Linqelio::analytics()->overview('2026-09-01', '2026-09-30');
$overview->kpi(AnalyticsMetric::DeliveryRate)?->value;   // null = nothing to measure, not 0

Linqelio::analytics()->timeseries(AnalyticsMetric::MessagesOut, AnalyticsGranularity::Day);
Linqelio::analytics()->breakdown(AnalyticsBreakdownBy::Channel);
Linqelio::analytics()->heatmap(MessageDirection::Inbound);
Linqelio::analytics()->campaign($campaignId)->funnel;    // recipients → sent → read → replied

$export = Linqelio::analytics()->export(
    AnalyticsExportRequest::breakdown(AnalyticsBreakdownBy::Agent, AnalyticsExportFormat::Xlsx)
        ->between('2026-09-01', '2026-09-30'),
);
Linqelio::analytics()->download($export->id);   // once it is ready
```

Numbers are computed in passes: `$overview->lastPassAt` says how fresh they are,
and `backfillStatus()` (an `AnalyticsBackfillStatus`; `isInProgress()` while
pending or running) whether history before the install is still being filled
in — a report over that past undercounts it until the backfill is `Done`.

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

Each error domain has its family: `ValidationException`, `AuthException`,
`TenancyException`, `ChannelException`, `PolicyException`, `MessageException`,
`ContactException`, `IdempotencyException`, `EmbedException`,
`ProviderException`, and for the newer surfaces `CampaignException` (campaigns
and scheduled sends), `AlertException`, `TemplateException`,
`AnalyticsException`, `AiException` and `ConversationException` (conversations
and groups). `ValidationException`, `ContactException` and `CampaignException`
carry `errors()` — field => reason — for `validation.*`,
`contact.field_invalid` and `campaign.invalid`.

Every exception's `retryAfter()` reads the response's `Retry-After` header, in
seconds (`PolicyException` prefers the problem's own figure).
`IdempotencyException` tells `isInProgress()` — retry the same request later —
from `isKeyReused()` — two different commands shared a key, a bug to fix.

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
