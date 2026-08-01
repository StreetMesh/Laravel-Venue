<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Venue\Http\ExperiencesController;

/*
 * One screen, at a name nothing else wants. The front page and the home page
 * are the application's, because a server has one of each and may offer more
 * than one capability.
 */
Route::get('experiences', ExperiencesController::class)->name('venue.experiences');
