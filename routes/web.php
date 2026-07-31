<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Venue\Http\LobbyController;

/*
 * Nothing here claims the front page either. Both halves being unable to claim
 * it is what makes them installable together.
 */
Route::get('/', LobbyController::class)->name('venue.lobby');
