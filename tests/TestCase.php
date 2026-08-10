<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Tests;

use Linqelio\Laravel\LinqelioServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LinqelioServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('linqelio.base_url', 'https://linqelio.test/v1');
        $app['config']->set('linqelio.key', 'cab-1.k-test.secret');
        $app['config']->set('linqelio.webhooks.secret', 'whsec-test');
        $app['config']->set('linqelio.retry.times', 1);
        $app['config']->set('linqelio.retry.sleep', 0);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
