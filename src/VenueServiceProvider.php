<?php

namespace StreetMesh\Venue;

use Illuminate\Support\ServiceProvider;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;

class VenueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Capabilities::class)->register(new VenueCapability);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'venue');

        $this->app['router']
            ->middleware('web')
            ->prefix((string) config('streetmesh.mount.venue', ''))
            ->group(__DIR__.'/../routes/web.php');
    }
}
