<?php

namespace StreetMesh\Venue\Gatherings;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\Laravel\Permissions\Tickets;

/**
 * Opening something, seating people at it, and letting them in.
 *
 * This is where the venue exercises the one authority the hub does not have:
 * deciding who may be somewhere. The hub can check that a venue said so; only
 * the venue can decide it in the first place, and only here is that decision
 * durable enough to survive the hub restarting.
 *
 * An experience puts its rules on top. What is here is true of chess, a watch
 * party and an auction alike: something is open, people are in it, and each of
 * them can be handed a way in.
 */
final class Gatherings
{
    public function __construct(private readonly Tickets $tickets) {}

    public function open(string $experience): Gathering
    {
        return Gathering::create([
            'experience' => $experience,
            'key' => (string) Str::ulid(),
            'status' => Gathering::OPEN,
        ]);
    }

    /**
     * Put somebody in it, or find them already there.
     *
     * Idempotent, because arriving twice is what a browser does — a reload, a
     * reconnection, a second tab — and none of those should be an error or a
     * second seat.
     */
    public function seat(Gathering $gathering, Delegation $visitor, string $seat = ''): Seat
    {
        if (! $gathering->isOpen()) {
            throw new RuntimeException('That is over.');
        }

        $existing = Seat::query()
            ->where('gathering_id', $gathering->id)
            ->where('delegation_id', $visitor->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        /*
         * Two people cannot both be white. Enforced by asking the database
         * rather than by looking first, because looking first leaves a gap
         * between the check and the write that two simultaneous arrivals fit
         * through exactly.
         */
        if ($seat !== '' && $this->taken($gathering, $seat)) {
            throw new RuntimeException("Somebody is already in the [{$seat}] seat.");
        }

        try {
            return Seat::create([
                'gathering_id' => $gathering->id,
                'delegation_id' => $visitor->id,
                'seat' => $seat,
            ]);
        } catch (QueryException) {
            throw new RuntimeException('That seat was taken a moment ago.');
        }
    }

    /**
     * A way in, for somebody who is already known to belong here.
     *
     * The ticket is minted from the seat rather than from the request, so what
     * it says is what this venue decided rather than what a browser asked for.
     */
    public function admit(Gathering $gathering, Delegation $visitor): string
    {
        $seat = Seat::query()
            ->where('gathering_id', $gathering->id)
            ->where('delegation_id', $visitor->id)
            ->whereNull('left_at')
            ->first();

        if ($seat === null) {
            throw new RuntimeException('That visitor has no place there.');
        }

        if (! $gathering->isOpen()) {
            throw new RuntimeException('That is over.');
        }

        return $this->tickets->mint($visitor, $gathering->room(), $seat->seat);
    }

    public function conclude(Gathering $gathering): Gathering
    {
        $gathering->update([
            'status' => Gathering::CONCLUDED,
            'concluded_at' => now(),
        ]);

        return $gathering->refresh();
    }

    private function taken(Gathering $gathering, string $seat): bool
    {
        return Seat::query()
            ->where('gathering_id', $gathering->id)
            ->where('seat', $seat)
            ->whereNull('left_at')
            ->exists();
    }
}
