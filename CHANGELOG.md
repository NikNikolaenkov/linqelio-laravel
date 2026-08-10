# Changelog

All notable changes to `linqelio/linqelio-laravel` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-08-11

### Added

- Typed client over the Linqelio API: channels, contacts, messages, media,
  conversations, webhook subscriptions and embed sessions.
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

[Unreleased]: https://github.com/NikNikolaenkov/linqelio-laravel/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/NikNikolaenkov/linqelio-laravel/releases/tag/v0.1.0
