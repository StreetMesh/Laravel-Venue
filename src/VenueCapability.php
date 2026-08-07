<?php

namespace StreetMesh\Venue;

use StreetMesh\Protocol\Laravel\Capabilities\Capability;
use StreetMesh\Protocol\Laravel\Capabilities\Widget;

/**
 * This server is somewhere people gather.
 *
 * It says so on the wire, offers something to greet strangers with, and offers
 * a panel for a signed-in person's home page. It does not decide where any of
 * that goes, because a server may offer more than one capability and only the
 * application can arrange them.
 */
final class VenueCapability implements Capability
{
    public function name(): string
    {
        return 'venue';
    }

    public function serviceType(): string
    {
        return 'StreetMeshVenue';
    }

    public function frontPage(): string
    {
        return 'venue::front';
    }

    /**
     * Arriving, which is not signing in — unless they already have.
     *
     * A venue holds no accounts. Somebody turns up with an address issued by
     * their own server and asks that server for permission, so the way in is a
     * box to type an address into; a login form would be a key to a lock this
     * server does not have.
     *
     * Somebody who has already arrived is a **visitor**, which is not the same
     * as being signed in — the framework's own `@auth` knows nothing about
     * them, so the front page went on offering the door to people standing
     * inside it. Only this package knows the difference, which is why the
     * question is answered here rather than in the page.
     *
     * @return array{label: string, route: string}
     */
    public function frontAction(): array
    {
        /*
         * Only where there is a session to ask about. Whether somebody is here
         * is a question about this browser, and a console command or anything
         * else without one is not a browser — it should get the plain answer
         * rather than an exception from inside the session store.
         */
        $arrived = request()->hasSession()
            && app(Visitors::class)->current(request()) !== null;

        return $arrived
            ? ['label' => 'Experiences', 'route' => 'venue.experiences']
            : ['label' => 'Connect', 'route' => 'venue.connect'];
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        return [new VenueWidget];
    }

    /**
     * @return array<int, array{label: string, route: string, icon?: string}>
     */
    public function navigation(): array
    {
        return [
            ['label' => 'Experiences', 'route' => 'venue.experiences'],
        ];
    }
}
