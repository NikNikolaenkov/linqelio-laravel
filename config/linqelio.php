<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API endpoint
    |--------------------------------------------------------------------------
    |
    | The base URL of your Linqelio installation, including the /v1 prefix.
    |
    | There is deliberately NO default. A wrong-but-working fallback would send
    | your customers' messages to somebody else's host, and that failure is
    | silent. An unset value raises Linqelio\Laravel\Exceptions\ConfigurationException
    | on the first call instead.
    |
    */

    'base_url' => env('LINQELIO_URL'),

    /*
    |--------------------------------------------------------------------------
    | Client API key
    |--------------------------------------------------------------------------
    |
    | Issued per cabinet, in the form "<cabinetId>.<keyId>.<secret>". Shown once
    | at creation — the platform stores only an argon2id hash of it, so a lost
    | key is reissued, never recovered.
    |
    | A key carries the "*" scope inside its own cabinet. Platform operations
    | (/cabinets) additionally need "platform:admin", which client keys are not
    | given by design.
    |
    */

    'key' => env('LINQELIO_KEY'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | retry.times applies to 429 and 5xx only — a 4xx is a statement about the
    | request, and repeating it just burns quota. Retry-After is honoured when
    | present, otherwise the delay grows exponentially from retry.sleep.
    |
    */

    'timeout' => (int) env('LINQELIO_TIMEOUT', 15),

    'upload_timeout' => (int) env('LINQELIO_UPLOAD_TIMEOUT', 60),

    'retry' => [
        'times' => (int) env('LINQELIO_RETRY_TIMES', 3),
        'sleep' => (int) env('LINQELIO_RETRY_SLEEP', 200), // ms, doubled per attempt
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Linqelio signs every delivery with X-Linqelio-Signature: sha256=<hex>,
    | an HMAC-SHA256 of the raw body. The secret is the one you supplied when
    | registering the endpoint.
    |
    | The payload carries routing identifiers only — never the message body.
    | Reading the message means an API call, which is why deliveries are queued
    | rather than handled inline: doing that round trip inside the webhook
    | request is how you end up timing out on the sender's side.
    |
    */

    'webhooks' => [
        'enabled' => env('LINQELIO_WEBHOOKS_ENABLED', true),
        'secret' => env('LINQELIO_WEBHOOK_SECRET'),
        'path' => env('LINQELIO_WEBHOOK_PATH', 'linqelio/webhook'),
        'middleware' => ['api'],

        // Reject deliveries older than this, so a captured request cannot be
        // replayed indefinitely.
        //
        // Applied as written when the platform sends X-Linqelio-Signature-V2,
        // because that signature covers the per-attempt send timestamp: a retry
        // arrives freshly stamped, a replay does not, and 300s is a real bound.
        //
        // Without v2 there is no authenticated per-attempt clock, so age has to
        // come from the payload's signed `occurredAt`, which is fixed at event
        // time. The platform's 6th and last attempt lands 930s after that, so on
        // this path a 960s floor is enforced regardless of the value here —
        // anything lower answers our own retries with a 401.
        //
        // Deliveries are remembered for at least 960s either way, so a late
        // retry is recognised as a repeat rather than processed twice.
        'tolerance' => (int) env('LINQELIO_WEBHOOK_TOLERANCE', 300), // seconds

        'queue' => [
            'connection' => env('LINQELIO_QUEUE_CONNECTION'),
            'queue' => env('LINQELIO_QUEUE', 'default'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message projection
    |--------------------------------------------------------------------------
    |
    | A local, queryable copy of the conversation history: join messages to your
    | own models, search them, report on them, render a thread without a network
    | call.
    |
    | It is a projection, not a source of truth. Rows are keyed by the platform's
    | message id (a ULID, stable forever) and never by chat id — a chat id can be
    | re-keyed underneath you when the platform canonicalises an identity, and a
    | projection built on it silently splits in two.
    |
    | Attachment bytes are not copied. The row keeps the message id; the content
    | is streamed from the API on demand.
    |
    */

    'projection' => [
        'enabled' => env('LINQELIO_PROJECTION', true),
        'table' => env('LINQELIO_PROJECTION_TABLE', 'linqelio_messages'),

        // Store outbound messages too, once the platform has accepted them.
        'outbound' => env('LINQELIO_PROJECTION_OUTBOUND', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embed widget
    |--------------------------------------------------------------------------
    |
    | Issues the short-lived session token the browser widget needs.
    |
    | The route exists so the token never has to be rendered into HTML: it is
    | scoped to a single contact, and HTML lands in page caches, browser history
    | and CDN logs. The widget fetches it over XHR and keeps it in memory only.
    |
    */

    'embed' => [
        'enabled' => env('LINQELIO_EMBED_ENABLED', false),
        'path' => env('LINQELIO_EMBED_PATH', 'linqelio/embed-token'),
        'middleware' => ['web', 'auth'],
    ],

];
