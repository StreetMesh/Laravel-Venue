<?php

return [

    /*
     |--------------------------------------------------------------------------
     | What this venue asks visitors for
     |--------------------------------------------------------------------------
     |
     | Named in ATProtocol's own grammar, because a scope invented locally is a
     | word no other server on the network understands. `atproto` is always
     | included and does not need listing.
     |
     |   repo:com.streetmesh.games.chess?action=create
     |
     | Ask for the least that works. `action=create` says a venue may add
     | records and never alter or remove them, and a visitor reading that on
     | their own server's consent screen can see the difference.
     |
     | Empty means this venue asks only to confirm who somebody is — which is
     | the right setting until an experience is installed that writes something.
     |
     */

    'scopes' => [],

    /*
     |--------------------------------------------------------------------------
     | Where the realtime half is
     |--------------------------------------------------------------------------
     |
     | The address a browser opens a websocket to. Sent to the browser with its
     | ticket rather than written into a page, so that moving the realtime half
     | is an operator's decision rather than an edit to somebody's experience
     | templates.
     |
     | Null means this venue hosts nothing live, which is a perfectly good venue
     | — a shop needs no room.
     |
     */

    'hub' => env('STREETMESH_HUB'),

    /*
    |--------------------------------------------------------------------------
    | What a hub says to this venue with
    |--------------------------------------------------------------------------
    |
    | Everything else between the two is one-way and needs no secret: a ticket
    | is signed here and merely verified there, and a result is asked for
    | rather than announced. This is the one direction that cannot work that
    | way — a hub telling a venue something has to be a hub the venue can
    | recognise, and a hub holds no key of its own.
    |
    | The same value goes wherever the hub runs. Locally that is this file:
    | `./hub-serve` reads it from here so there is one copy rather than two
    | that have to agree.
    |
    | A comma-separated list is accepted, and that is how it is rotated: add
    | the new one, deploy both sides, take the old one off. Replacing a single
    | value in place means a moment where one side has changed and the other
    | has not, which is an outage.
    |
    | This venue will not serve requests without one, because a venue that
    | started anyway would look healthy and quietly never hear that a game had
    | ended.
    |
    */

    'secret' => env('STREETMESH_REALTIME_SECRET'),

    /*
     |--------------------------------------------------------------------------
     | Who may see what is on
     |--------------------------------------------------------------------------
     |
     | `anybody` — the menu is public. Somebody can look at what this venue
     | offers before deciding whether to hand over a name, which is how a venue
     | works in the world: a chess club posts its programme on the door.
     |
     | `visitors` — nothing is shown until somebody has arrived. For a venue
     | that is private about what it hosts, or whose menu is only meaningful to
     | people who are already members of something.
     |
     | Either way the experiences themselves are still behind the door. Seeing
     | that chess is on offer is not the same as sitting down at a table, and
     | somebody who clicks through from a public menu is sent to arrive and then
     | brought back to where they were going.
     |
     */

    'gallery' => env('STREETMESH_VENUE_GALLERY', 'anybody'),

    /*
     |--------------------------------------------------------------------------
     | Where to send somebody who has no address yet
     |--------------------------------------------------------------------------
     |
     | A venue cannot house anybody. Arriving here means holding a name that
     | some domicile issued, so a visitor who has never had one is standing at a
     | door they cannot open, and the only useful thing to tell them is where to
     | go and get one.
     |
     | Which domicile is the operator's call, and it is a recommendation rather
     | than a rule — an address from anywhere works. This is only the answer to
     | "I do not have one of those".
     |
     | A hostname, not a URL, because it is the same shape as the address being
     | asked for in the field above it.
     |
     | Null takes the offer off the screen, for a venue whose visitors already
     | live somewhere.
     |
     */

    'domicile' => env('STREETMESH_VENUE_DOMICILE', 'stme.sh'),

    /*
     |--------------------------------------------------------------------------
     | What this venue is called, in pictures
     |--------------------------------------------------------------------------
     |
     | A venue is the half of a server that strangers meet, and often the half
     | with a name of its own: Tabletop runs on StreetMesh the way a shop stands
     | on a high street. A domicile in the same container sets its own, so the
     | server answering for somebody's records is not wearing the sign over the
     | door of the games room.
     |
     | A public path with no variant or extension on it. A mark that carries its
     | own ground needs a second drawing for a dark surface, and every pack built
     | for this server puts `-small.svg` and `-dark-small.svg` beside each other
     | under one name — so naming the pair is enough, and there are not two paths
     | here that can disagree with each other.
     |
     | Unset is the server's own mark, which is the right answer for a venue
     | nobody has branded separately.
     |
     */

    'mark' => env('STREETMESH_VENUE_MARK'),

    /*
     |--------------------------------------------------------------------------
     | Building this server's hub
     |--------------------------------------------------------------------------
     |
     | A StreetMesh server has at most one hub, and what makes it this server's
     | hub is the rooms it serves. Only this server knows which those are, so
     | `php artisan hub:build` writes it out — flat and self-contained, with
     | everything copied in.
     |
     | `hub` is where the hub library lives and `into` is where the artifact is
     | written. Both default to the sensible place in a checkout, and are here
     | for a server arranged differently.
     |
     */
    'build' => [

        'hub' => env('STREETMESH_HUB_SOURCE'),

        'into' => env('STREETMESH_HUB_BUILD'),

    ],

];
