# Changelog

All notable changes to `linqelio/linqelio-laravel` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/linqelio/linqelio-laravel/commits/main
