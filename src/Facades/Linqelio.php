<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Linqelio\Laravel\Resources\ChannelsResource channels()
 * @method static \Linqelio\Laravel\Resources\ContactsResource contacts()
 * @method static \Linqelio\Laravel\Resources\MessagesResource messages()
 * @method static \Linqelio\Laravel\Resources\MediaResource media()
 * @method static \Linqelio\Laravel\Resources\ConversationsResource conversations()
 * @method static \Linqelio\Laravel\Resources\EmbedResource embed()
 * @method static \Linqelio\Laravel\Client\HttpClient client()
 *
 * @see \Linqelio\Laravel\Linqelio
 */
final class Linqelio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'linqelio';
    }
}
