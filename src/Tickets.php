<?php

namespace StreetMesh\Venue;

use RuntimeException;
use StreetMesh\Protocol\Laravel\Attestations\Attestations;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;

/**
 * A permission slip to sit somewhere, for the realtime half of this venue.
 *
 * Everything hard has already happened here: a federated address was resolved,
 * a delegation was checked, somebody was seated. The realtime half can do none
 * of that and should never learn how — so it is told the answer, signed with
 * the key this venue already publishes, and has only a signature to check.
 *
 * That is what keeps the join path free of shared secrets. The realtime process
 * holds no credential, cannot impersonate this venue, and cannot assert
 * anything back: everything it knows arrived signed by somebody else.
 *
 * Short-lived on purpose. A ticket is good for long enough to open a websocket
 * and not long enough to be worth stealing — and because it names one room, a
 * stolen one opens one door rather than the building.
 */
final class Tickets
{
    public const LIFETIME_SECONDS = 60;

    public function __construct(
        private readonly Identities $identities,
        private readonly Attestations $attestations,
    ) {}

    /**
     * @param  string  $room  the room being joined, compared rather than trusted
     *                        at the other end
     */
    public function mint(Delegation $visitor, string $room, string $seat = ''): string
    {
        if ($visitor->did === null) {
            throw new RuntimeException('Somebody who has not been seated cannot be given a ticket.');
        }

        $identity = $this->identities->forServer();

        return $this->attestations->issue([
            /*
             * Who, and what to call them. The name is this venue's word rather
             * than the visitor's, because a name a person types is a name they
             * can choose to be somebody else's.
             */
            'sub' => $visitor->did,
            'name' => $visitor->handle,

            'room' => $room,
            'seat' => $seat,

            'exp' => now()->addSeconds(self::LIFETIME_SECONDS)->getTimestamp(),
            'jti' => bin2hex(random_bytes(16)),
        ], $identity->key(), $identity->keyId());
    }
}
