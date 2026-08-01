<?php

namespace StreetMesh\Venue\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class ExperiencesController
{
    public function __invoke(Factory $views): View
    {
        return $views->make('venue::experiences');
    }
}
