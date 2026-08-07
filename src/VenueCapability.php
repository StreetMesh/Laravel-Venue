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
     * Arriving, which is not signing in.
     *
     * A venue holds no accounts. Somebody turns up with an address issued by
     * their own server and asks that server for permission — so the way in is
     * a box to type an address into, and a login form would be a key to a lock
     * this server does not have.
     *
     * @return array{label: string, route: string}
     */
    public function frontAction(): array
    {
        return ['label' => 'Connect', 'route' => 'venue.connect'];
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
