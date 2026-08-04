<?php

namespace StreetMesh\Venue;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;

class VenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Merged under the protocol's own key rather than a second root, so an
         * operator configuring a server reads one file rather than one per
         * package installed.
         */
        $this->mergeConfigFrom(__DIR__.'/../config/venue.php', 'streetmesh.venue');

        $this->app->singleton(Visitors::class);
        $this->app->singleton(Experiences\Experiences::class);
        $this->app->singleton(Gatherings\Gatherings::class);
    }

    public function boot(): void
    {
        $this->app->make(Capabilities::class)->register(new VenueCapability);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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
         * "Is somebody here", which is not the same question as "is somebody
         * signed in". A venue has no accounts, so the framework's own guards
         * have nothing to check — what this asks is whether this browser is
         * acting under permission somebody's own server gave us.
         */
        $this->app['router']->aliasMiddleware('visitor', Http\RequireVisitor::class);

        /*
         * Whether the menu is anybody's business, asked per request rather than
         * when routes are registered — a setting consulted at boot gets baked
         * into a cached route table and appears to do nothing.
         */
        $this->app['router']->aliasMiddleware('venue.menu', Http\GuardTheMenu::class);

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
