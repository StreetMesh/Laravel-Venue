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

];
