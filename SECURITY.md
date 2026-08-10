# Security Policy

## Reporting a vulnerability

Please do not open a public issue.

Report privately through GitHub's [security advisory
form](https://github.com/NikNikolaenkov/linqelio-laravel/security/advisories/new), or
email **security@linqelio.com**.

Include what you can: affected version, a reproduction, and what an attacker
gets out of it. You will get an acknowledgement within three working days and an
assessment within seven.

## Supported versions

The latest minor release receives security fixes.

## What this package handles

Three things are worth knowing if you are auditing it:

**API keys.** Read from configuration and sent as a Bearer token. Never logged,
never persisted by this package. A key grants full access inside its cabinet, so
treat it the way you would a database password.

**Webhook signatures.** Verified as HMAC-SHA256 over the raw request body, using
`hash_equals` for constant-time comparison. Deliveries older than the configured
tolerance are rejected, and event ids are remembered for the same window so a
captured request cannot be replayed.

**Attachments.** Streamed through the platform rather than served from object
storage. `BinaryResponse::toResponse()` always sets `X-Content-Type-Options:
nosniff`, because the content type of an inbound attachment comes from whoever
sent it — serving it unguarded on your own origin is how a file claiming to be
HTML ends up executing there.

## What it does not handle

The package does not store message content anywhere except the projection table
you opt into, and does not copy attachment bytes at all. Retention, access
control and deletion of conversation data remain the platform's responsibility —
and, for the projection, yours.
