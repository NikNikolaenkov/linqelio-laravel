<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Linqelio\Laravel\Http\EmbedTokenController;
use Linqelio\Laravel\Webhooks\VerifySignature;
use Linqelio\Laravel\Webhooks\WebhookController;

if (config('linqelio.webhooks.enabled', true)) {
    Route::post(config('linqelio.webhooks.path', 'linqelio/webhook'), WebhookController::class)
        ->middleware(array_merge(
            (array) config('linqelio.webhooks.middleware', ['api']),
            [VerifySignature::class],
        ))
        ->name('linqelio.webhook')
        // CSRF applies to browser forms; a signed server-to-server delivery has
        // no session to protect, and the signature is the stronger proof anyway.
        ->withoutMiddleware([VerifyCsrfToken::class]);
}

if (config('linqelio.embed.enabled', false)) {
    Route::get(config('linqelio.embed.path', 'linqelio/embed-token'), EmbedTokenController::class)
        ->middleware((array) config('linqelio.embed.middleware', ['web', 'auth']))
        ->name('linqelio.embed-token');
}
