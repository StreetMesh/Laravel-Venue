<?php

namespace StreetMesh\Venue\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * A plain controller, for now.
 *
 * This screen was written as a Livewire single-file component resolved through
 * this package's view namespace, on the assumption that Livewire treats a
 * package namespace as it treats the application's own. It does not — the
 * component is simply not found — so the mechanism for a package to ship
 * Livewire components is still open, and shipping a broken screen while it is
 * settled would be the worse trade.
 */
class ExperiencesController
{
    public function __invoke(Factory $views): View
    {
        return $views->make('venue::experiences');
    }
}
