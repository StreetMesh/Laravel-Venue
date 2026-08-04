<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Venue\Http\TicketController;
use StreetMesh\Venue\Http\VisitController;

/*
 * The door.
 *
 * Nobody has an account here, so there is no register, no password reset and
 * nothing to confirm by email. There is a text field for a name issued
 * somewhere else, a trip to that server to be asked, and a way back.
 */
Route::livewire('visit', 'venue::visit')->name('venue.visit');

Route::post('visit', [VisitController::class, 'start'])->name('venue.visit.start');

/*
 * Where their own server sends them back to. Named in the client metadata
 * document this server publishes, which is why it cannot be changed without
 * that document changing too — a redirect a venue has not published is one a
 * domicile will refuse to use.
 */
Route::get('visit/callback', [VisitController::class, 'callback'])->name('venue.callback');

Route::post('leave', [VisitController::class, 'leave'])->name('venue.leave');

/*
 * One screen, at a name nothing else wants, drawn by a Livewire component this
 * package ships itself. It renders into the host's layout — the package decides
 * what the screen is, the application decides what frames it.
 *
 * Behind the door only if an operator says so. A menu is a thing venues put
 * where people can read it, so the default is that anybody may — and going into
 * an experience still means arriving first.
 */
Route::livewire('experiences', 'venue::experiences')
    ->middleware('venue.menu')
    ->name('venue.experiences');

/*
 * A way in to something happening here.
 *
 * Behind the door, because a ticket names a visitor and there is no visitor to
 * name until somebody has arrived. What it hands back is checked by the hub
 * against the venue's signature, so this endpoint decides and the hub only
 * verifies.
 */
Route::post('gatherings/{key}/ticket', TicketController::class)
    ->middleware('visitor')
    ->name('venue.ticket');
