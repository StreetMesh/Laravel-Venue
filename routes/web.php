<?php

use Illuminate\Support\Facades\Route;

/*
 * One screen, at a name nothing else wants, drawn by a Livewire component this
 * package ships itself. It renders into the host's layout — the package decides
 * what the screen is, the application decides what frames it.
 */
Route::livewire('experiences', 'venue::experiences')->name('venue.experiences');
