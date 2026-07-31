<?php

namespace StreetMesh\Venue\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use StreetMesh\Protocol\Laravel\ProtocolServiceProvider;
use StreetMesh\Venue\VenueServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ProtocolServiceProvider::class, VenueServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('streetmesh.host', 'games.test');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/streetmesh/protocol-laravel/database/migrations');
    }
}
