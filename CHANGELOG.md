# Changelog

All notable changes to `linqelio/linqelio-laravel` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `webhooks()->register()` now returns `RegisteredWebhook` instead of `Webhook`.
  The subscription moved to `->subscription` (with `->id()` as a shortcut), and
  the new `->secret` carries a signing key when the platform minted one. Code
  that reads the return value needs updating; code that ignores it does not.

  A separate type rather than a nullable field on `Webhook`, mirroring the
  platform's own split: `Webhook` is what every read returns and it carries no
  key, ever — a nullable property on it would soften that into "usually null".

### Added

- `webhooks()->register()` takes the signing key itself as `secret`, and asks the
  platform to mint one when given neither `secret` nor `secretRef`.

  Until the platform grew this, registering a working subscription through the
  API was not possible. The contract accepted only `secretRef` — an address in
  the platform's secret store — and no API call could put a key at one, so the
  only registration an application could complete carried an empty reference. An
  empty reference is exactly what makes the platform deliver **unsigned**, which
  this package's own middleware rejects on arrival, every time. The example in
  this README was one of those calls.

  Passing both is refused before the request, and so is a `secret://…` string
  passed as `secret` — sent that way the platform stores and signs with it
  verbatim, and every delivery then fails verification here for a reason nothing
  in the logs explains.

- `Data\RegisteredWebhook`, the result type above.

### Fixed

- A platform carrying the same change no longer downgrades to an unsigned
  delivery when a subscription has a signing key it momentarily cannot resolve —
  it retries instead. Nothing changes in this package; the middleware was already
  rejecting those. What stops is the rejections arriving for deliveries that were
  supposed to be signed.

## [0.3.0] - 2026-08-11

### Changed

- `ErasureResult::toArray()` now emits two more keys, `outbox` and `media`. Code
  that compares the array strictly — an erasure journal asserting an exact shape,
  as this package's own test did — will need updating. The counts themselves are
  unchanged.
- The webhook freshness window is enforced differently. `webhooks.tolerance`
  still defaults to 300s and now applies as written when the platform sends
  `X-Linqelio-Signature-V2`; without it a 960s floor is imposed, because age is
  then measured from a timestamp fixed at event time and anything lower rejects
  the platform's own retries.

### Added

- `messages()->find()` and the `MessageStatusChanged` event, together answering
  what a send actually did. `send()` returns when the platform accepts the
  command, not when the provider is reached — and until the platform grew
  `message.status` and `GET /messages/{id}` there was no way to learn the
  outcome, so a failed send looked exactly like a delivered one. Subscribe to
  `message.status` when registering the webhook; every transition is delivered
  and `eventTypes` filters the ones you do not want.
- `Message::failureReason()`, and `Message::$meta` behind it. "Failed" is a
  status; the reason is the answer, and without it the next step was reading
  platform logs an integrator has no access to.
- `LinqelioMessage::recordStatus()` advances the projection when a status
  arrives. Only the status moves: a status payload carries no body, so writing
  the rest of the row from it would blank the content the projection holds.
- `ErasureResult::$outbox` and `$media` — the two copies of a person that
  outlive the redaction. A journal entry without them describes half an erasure.
- Verification of `X-Linqelio-Signature-V2`, which the platform signs over the
  send timestamp and delivery id as well as the body. Nothing to configure: a
  delivery that carries it is judged on those headers — freshness per attempt,
  single-use on the delivery id — and one that does not falls back to the signed
  payload as before. This is what lets the freshness window stay at 300s without
  rejecting the platform's own retries.
- `Linqelio::forKey()` and `HttpClient::withKey()`, for applications serving more
  than one cabinet. The container binds a single client built from
  `linqelio.key`, and under a persistent worker it outlives the request that
  resolved it — so the key has to travel with the call instead of being swapped
  on the shared instance, where the next request would inherit it.

### Fixed

- `messages()->history()` and `conversations()->feed()` always returned an empty
  page. Both read the response's `items`, but a message page arrives under
  `messages`; only the conversation and contact lists use `items`. Since the
  webhook deliberately carries no message body and there is no
  `GET /messages/{id}`, these two calls are the only way to read one — so this
  made inbound content unreadable rather than merely awkward.
- Pagination was ignored, silently returning the first page forever.
  `history()` sent `cursor` where the contract takes `before`, and
  `contacts()->list()` sent `cursor` where it takes `since`. Both arguments keep
  their names; only the wire parameter changed.
- Webhook freshness rejected the platform's own retries. Age is measured from the
  payload's signed `occurredAt`, which does not reset between attempts, while the
  deliverer backs off up to 930s before its last one — so with the old 300s
  tolerance attempts 5 and 6 were answered 401 and the delivery dead-lettered.
  Anything replayed through `POST /channels/{id}/sync` was refused outright. The
  default tolerance is now 1800s, with a 960s floor enforced in code.
- Webhook single-use keys no longer depend on `X-Linqelio-Delivery`. The
  signature covers the body alone, so that header is attacker-supplied; the key
  now comes from the signed payload, falling back to a hash of the signed body
  for events carrying no id. Deliveries are remembered for the full tolerance
  window, closing the gap where a capture was too new to be stale and too old to
  be recalled.
- `channels()->create()` sent the label as `name`, but `CreateChannelRequest`
  declares `label` and the platform reads only that — so every channel created
  through this package was left unlabelled.
- `HttpClient::VERSION` still read `0.1.0` after the 0.2.0 release, so the
  `User-Agent` misreported the client version.

### Now honoured by the platform

Two arguments this package sent that the platform used to discard. Both work as
documented; the docblocks that warned against relying on them are gone, and the
parity gate's staleness check is what noticed on the first contract sync after
the fixes landed:

- `contacts()->update(version:)` — optimistic concurrency. Pass the version you
  last read and the write applies only if nothing changed underneath, otherwise
  `contact.version_conflict`. Omit it and the update stays last-writer-wins.
- `embed()->session(capabilities:, conversationId:)` — the issued token is never
  wider than what you ask for, and a capability the platform does not issue is
  refused rather than quietly dropped. `conversationId` confines the token to one
  thread.

### Testing

- The contract parity gate now parses the contract instead of pattern-matching
  it, and drives all 25 covered operations against a faked platform. Three new
  checks compare what actually goes on the wire: query parameters, request body
  fields, and the response field each page is read from. All three of the wire
  defects fixed above were invisible to the old gate; the first run of the new
  one found the `label` bug and both deviations documented above.

## [0.2.0] - 2026-08-11

### Added

- `contacts()->erase()` for subject deletion requests. Not a delete of the
  contact record — messages hold the person's number in their own columns with no
  link back to cascade through, so the platform redacts them in one transaction.
  Returns the counts to record, and is idempotent so a retry after a timeout is
  safe. It cannot reach copies in your own database, including this package's
  message projection.
- `webhooks()` covering the whole subscription lifecycle: list, register,
  disable/enable and delete. Disabling keeps the subscription and its signing
  key, so recovering from a broken endpoint does not mean handing out a new one.
- `channels()->settings()` for non-secret provider configuration (the WhatsApp
  Cloud sending number), and `Channel::$phoneNumberId` reading it back — the
  mirror of credentials, which never read back.
- `channels()->setCredentials()` takes the WhatsApp Cloud app secret and verify
  token alongside the access token. All three are per-channel, so a tenant can
  run its own Meta app; send them together, since a channel with a token but no
  app secret sends fine and rejects every message coming back.

### Fixed

- Documentation links pointed at a repository that does not exist. The
  security policy sent vulnerability reports to a 404, so a finder had no
  private channel to use.

## [0.1.0] - 2026-08-10

### Added

- Typed client over the Linqelio API: channels, contacts, messages, media,
  conversations and embed sessions.
- `Idempotency-Key` on every unsafe command, generated when not supplied and
  derived from the job id on queued sends, so a retry cannot become a duplicate
  message.
- Typed exceptions per error domain, selected from the code's domain rather than
  an exhaustive match — a code newer than this package still lands in the right
  family instead of degrading to the base class.
- Webhook receiver with HMAC-SHA256 verification over the raw body, freshness and
  replay checks, and queued handling.
- `MessageReceived` with lazy `message()` and `contact()`: the delivery carries
  routing identifiers only, so the body is fetched only when a listener asks.
- Local message projection (`linqelio_messages`), keyed by the platform's message
  id, with `linqelio:backfill` for history that predates the install.
- `HasLinqelioContact` for host models, `SendMessage` job, and the
  `linqelio:channels` / `linqelio:send` commands.
- Contract parity test: every operation is wrapped or explicitly excluded with a
  reason, and every error code in the contract exists in `ErrorCode`.

[Unreleased]: https://github.com/NikNikolaenkov/linqelio-laravel/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/NikNikolaenkov/linqelio-laravel/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/NikNikolaenkov/linqelio-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/NikNikolaenkov/linqelio-laravel/releases/tag/v0.1.0
