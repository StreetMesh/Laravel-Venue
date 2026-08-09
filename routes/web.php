<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Venue\Http\ConnectController;
use StreetMesh\Venue\Http\TicketController;

/*
 * The door.
 *
 * Nobody has an account here, so there is no register, no password reset and
 * nothing to confirm by email. There is a text field for a name issued
 * somewhere else, a trip to that server to be asked, and a way back.
 */
Route::livewire('connect', 'venue::connect')->name('venue.connect');

/*
 * What this venue looks like, at the address every venue publishes it at.
 *
 * A domicile that has never heard of this server needs a picture of it for one
 * screen: the moment somebody is asked whether to let it in. It builds this
 * address from the hostname it already has, so there is nothing to negotiate
 * and nothing to validate — the only party who can put a picture here is the
 * one the picture is about, and that is the whole of why it is worth showing.
 *
 * A redirect rather than a copy. The mark is already a public asset and an
 * operator may point the configuration at any of them; serving the bytes twice
 * would be a second place for it to go stale.
 *
 * Outside the door on purpose. A permission screen is being read by somebody
 * who has never been here and may well decide not to come.
 */
Route::get('mark.svg', fn () => redirect(asset(app(Capabilities::class)->mark('venue')->light())))
    ->name('venue.mark');

Route::get('mark-dark.svg', fn () => redirect(asset(app(Capabilities::class)->mark('venue')->dark())))
    ->name('venue.mark.dark');

Route::post('connect', [ConnectController::class, 'start'])->name('venue.connect.start');

/*
 * Where their own server sends them back to.
 *
 * Its *name* is what the client metadata document publishes, looked up when
 * that document is served — so this path can be moved and the two cannot
 * disagree. They could once, and a redirect a venue has not published is one a
 * domicile refuses, with the refusal arriving from somebody else's server.
 */
Route::get('connect/callback', [ConnectController::class, 'callback'])->name('venue.callback');

Route::post('leave', [ConnectController::class, 'leave'])->name('venue.leave');

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
 * Not behind the door, and it used to be. A ticket names a visitor, so this
 * looked like something only an arrival could ask for — but watching is not
 * arriving, and a gathering whose experience says anybody may look at it has to
 * be lookable at by somebody who has never been here. Behind `visitor`, the
 * middleware met every passer-by with a form asking them to name their own
 * server, in order to watch a game of chess.
 *
 * Nothing is decided here. Who may be let in is the experience's answer about
 * its own gathering, and what this hands back is checked by the hub against the
 * venue's signature — so a ticket this should not have issued is one the hub
 * still cannot be talked out of checking.
 */
Route::post('gatherings/{key}/ticket', TicketController::class)
    ->name('venue.ticket');
