<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Venue\Http\ExperiencesController;

/*
 * One screen, at a name nothing else wants. Drawn by a controller rather than a
 * Livewire component until the mechanism for a package to ship one is settled —
 * see ExperiencesController.
 */
Route::get('experiences', ExperiencesController::class)->name('venue.experiences');
