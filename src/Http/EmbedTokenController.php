<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Hands the widget its session token over XHR.
 *
 * Rendering the token into a Blade view would be simpler and worse: it is scoped
 * to one person's conversation, and HTML settles into page caches, browser
 * history and CDN logs. Fetched over XHR it can live in memory and nowhere else.
 *
 * Which contact the caller may open is the application's decision, not this
 * package's — resolve it from the authenticated user. The default below refuses
 * rather than guesses.
 */
final class EmbedTokenController
{
    /**
     * @var null|callable(Request): ?string
     */
    private static $resolver = null;

    /**
     * Teach the package how to map the current user to a contact id.
     *
     * Call from a service provider:
     *
     *   EmbedTokenController::resolveContactUsing(
     *       fn (Request $request) => $request->user()?->linqelio_contact_id
     *   );
     *
     * @param  callable(Request): ?string  $resolver
     */
    public static function resolveContactUsing(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (self::$resolver === null) {
            return new JsonResponse([
                'error' => 'No contact resolver configured. Call '.
                    'EmbedTokenController::resolveContactUsing() to say which contact '.
                    'the authenticated user may open.',
            ], 501);
        }

        $contactId = (self::$resolver)($request);

        if (! is_string($contactId) || $contactId === '') {
            return new JsonResponse(['error' => 'No contact for this user'], 403);
        }

        $capabilities = array_values(array_filter(
            (array) $request->query('cap', []),
            static fn ($c): bool => is_string($c) && $c !== '',
        ));

        return new JsonResponse(Linqelio::embed()->session($contactId, $capabilities));
    }
}
