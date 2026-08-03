<?php

namespace StreetMesh\Venue\Tests;

use Flux\FluxServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use StreetMesh\Protocol\Laravel\ProtocolServiceProvider;
use StreetMesh\Venue\VenueServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        // Livewire and Flux are listed because this package ships components
        // written in them, and testbench boots only what it is told about.
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            ProtocolServiceProvider::class,
            VenueServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('streetmesh.host', 'games.test');

        /*
         * A stand-in for the host's chrome.
         *
         * This package ships screens written against the Livewire starter kit's
         * layout, which is the opinion the project settled on — so it cannot
         * render one of its own screens without a host. Pointing the `layouts`
         * namespace at a stub is what Livewire itself does with the real one on
         * boot, so this stands in the same way rather than a different way.
         */
        $app['config']->set('livewire.component_namespaces.layouts', __DIR__.'/fixtures/views/layouts');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/streetmesh/protocol-laravel/database/migrations');

        // This package's own, which hold what a venue remembers about what is
        // happening here.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
