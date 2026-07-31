<?php

namespace StreetMesh\Venue;

use StreetMesh\Protocol\Laravel\Capabilities\Capability;

/**
 * This server is somewhere people gather.
 *
 * Nobody has an account here. Everyone arrives with a name issued somewhere
 * else, does something, and leaves with a record of it — which is why a venue
 * needs an identity of its own to sign with but no notion of a resident.
 */
final class VenueCapability implements Capability
{
    public function name(): string
    {
        return 'venue';
    }

    public function serviceType(): string
    {
        // No ATProtocol equivalent, because a place people visit to do things
        // together is the part StreetMesh is adding rather than adopting.
        return 'StreetMeshVenue';
    }

    public function home(): string
    {
        return 'venue.lobby';
    }

    /**
     * @return array<int, array{label: string, route: string, icon?: string}>
     */
    public function navigation(): array
    {
        return [
            ['label' => 'Lobby', 'route' => 'venue.lobby', 'icon' => 'users'],
        ];
    }
}
