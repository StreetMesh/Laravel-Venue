<?php

namespace StreetMesh\Venue\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Protocol\Laravel\Identity\Identities;

class LobbyController
{
    public function __invoke(Factory $views, Identities $identities, Capabilities $capabilities): View
    {
        return $views->make('venue::lobby', [
            'identity' => $identities->forServer(),
            'navigation' => $capabilities->navigation(),
        ]);
    }
}
