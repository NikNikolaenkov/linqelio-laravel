<?php

declare(strict_types=1);

namespace Linqelio\Laravel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Linqelio\Laravel\Client\HttpClient;

final class LinqelioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/linqelio.php', 'linqelio');

        $this->app->singleton(HttpClient::class, function ($app): HttpClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('linqelio');

            return new HttpClient(
                http: $app->make(HttpFactory::class),
                baseUrl: is_string($config['base_url'] ?? null) ? $config['base_url'] : null,
                key: is_string($config['key'] ?? null) ? $config['key'] : null,
                timeout: (int) ($config['timeout'] ?? 15),
                uploadTimeout: (int) ($config['upload_timeout'] ?? 60),
                retry: [
                    'times' => (int) ($config['retry']['times'] ?? 3),
                    'sleep' => (int) ($config['retry']['sleep'] ?? 200),
                ],
            );
        });

        $this->app->singleton(Linqelio::class, fn ($app): Linqelio => new Linqelio($app->make(HttpClient::class)));
        $this->app->alias(Linqelio::class, 'linqelio');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/linqelio.php');

        // Loaded rather than published: the projection's shape is the package's
        // concern, and an application that edited its own copy would silently
        // drift from what the model expects. Publish it (below) only to change
        // that deliberately.
        if ((bool) config('linqelio.projection.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/linqelio.php' => config_path('linqelio.php'),
            ], 'linqelio-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'linqelio-migrations');

            $this->commands([
                Console\ChannelsCommand::class,
                Console\SendTestCommand::class,
                Console\BackfillCommand::class,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [HttpClient::class, Linqelio::class, 'linqelio'];
    }
}
