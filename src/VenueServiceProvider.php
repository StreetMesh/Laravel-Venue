<?php

namespace StreetMesh\Venue;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;

class VenueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Capabilities::class)->register(new VenueCapability);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'venue');

        /*
         * Livewire keeps its own register of namespaces, separate from Blade's.
         * `loadViewsFrom` above is what makes `venue::front` resolvable as a
         * view; it does nothing for `<livewire:venue::experiences />`, because
         * Livewire's finder consults only what `addNamespace` gave it. Both are
         * needed, and this is exactly how Livewire registers its own `pages`
         * and `layouts` namespaces on boot.
         *
         * No ⚡ in the filename on purpose — an emoji in a path that Composer
         * has to install is a problem nobody needs.
         */
        Livewire::addNamespace('venue', viewPath: __DIR__.'/../resources/views/livewire');

        /*
         * At its own name, with no prefix. There is nothing here another
         * capability would also want, so there is nothing to arrange — the two
         * surfaces that overlap, the front page and the home page, belong to
         * the application.
         */
        $this->app['router']
            ->middleware('web')
            ->group(__DIR__.'/../routes/web.php');
    }
}
