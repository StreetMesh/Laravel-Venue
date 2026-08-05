<?php

namespace StreetMesh\Venue\Tests;

use RuntimeException;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\P256;
use StreetMesh\Venue\Gatherings\Gatherings;
use StreetMesh\Venue\Visitors;

/**
 * The venue deciding who may be somewhere.
 *
 * This is the one authority the hub does not have. A hub can check that a venue
 * said somebody may sit down; only the venue can decide it, and only here is
 * that decision durable enough to survive the hub restarting.
 */
class GatheringTest extends TestCase
{
    private const CHESS = 'com.streetmesh.games.chess';

    private function gatherings(): Gatherings
    {
        return $this->app->make(Gatherings::class);
    }

    private function visitor(string $who = 'alice'): Delegation
    {
        return Delegation::create([
            'did' => 'did:web:'.$who.'.home.test',
            'handle' => $who.'.home.test',
            'issuer' => 'https://home.test',
            'dpop_key' => Delegation::store(P256::generate()),
            'access_token' => 'a-live-token',
            'scope' => 'atproto',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * A delegation is one trip through the door, not a person.
     *
     * Coming back — tomorrow, or in another browser — mints a fresh one for the
     * same human. Keyed on the delegation, this venue sat one person down twice
     * and reported "2 at the table" with nobody else in the building. The
     * second chair was the first person returning, and they went on to play
     * themselves while a real visitor was put in the audience.
     */
    public function test_somebody_coming_back_returns_to_their_own_chair(): void
    {
        $gathering = $this->gatherings()->open('com.streetmesh.games.chess');
        $alice = $this->visitor();

        $first = $this->gatherings()->seat($gathering, $alice, 'white');

        // The same person, a second time through the door.
        $again = $this->visitor();

        $this->assertNotSame($alice->id, $again->id);
        $this->assertSame($alice->did, $again->did);

        $second = $this->gatherings()->seat($gathering, $again, 'black');

        $this->assertSame($first->id, $second->id, 'one person, one chair');
        $this->assertSame('white', $second->seat, 'and it is the chair they were already in');
        $this->assertSame(1, $gathering->seats()->count());
    }

    /**
     * The chair follows them to the permission they are actually holding.
     *
     * The one they sat down with may have expired while they were away, and
     * settling against it would fail at the last step — after the game was over
     * and there was nothing to be done about it.
     */
    public function test_the_chair_takes_up_the_permission_they_came_back_with(): void
    {
        $gathering = $this->gatherings()->open('com.streetmesh.games.chess');

        $this->gatherings()->seat($gathering, $this->visitor(), 'white');

        $again = $this->visitor();
        $seat = $this->gatherings()->seat($gathering, $again, 'white');

        $this->assertSame($again->id, $seat->delegation_id);
    }

    /**
     * The hub keeps nothing. A room is memory and is gone when the last person
     * leaves, so a venue that recorded only "concluded" could say a gathering
     * was over and nothing about it — which is what somebody coming back to
     * look at a finished game would be shown.
     */
    public function test_a_concluded_gathering_remembers_how_it_ended(): void
    {
        $gathering = $this->gatherings()->open('com.streetmesh.games.chess');

        $this->assertNull($gathering->outcome);

        $concluded = $this->gatherings()->conclude($gathering, [
            'outcome' => 'resignation',
            'winner' => 'white',
        ]);

        $this->assertSame('resignation', $concluded->outcome['outcome']);
        $this->assertSame('white', $concluded->outcome['winner']);
        $this->assertFalse($concluded->isOpen());
    }

    public function test_a_gathering_is_named_by_its_experience_and_which_one(): void
    {
        $gathering = $this->gatherings()->open(self::CHESS);

        $this->assertStringStartsWith(self::CHESS.'/', $gathering->room());
        $this->assertTrue($gathering->isOpen());
    }

    /**
     * A reload, a reconnection, a second tab — all of them arrive again, and
     * none of them should be an error or a second seat.
     */
    public function test_arriving_twice_is_the_same_seat(): void
    {
        $gatherings = $this->gatherings();
        $gathering = $gatherings->open(self::CHESS);
        $alice = $this->visitor();

        $first = $gatherings->seat($gathering, $alice, 'white');
        $again = $gatherings->seat($gathering, $alice, 'white');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, $gathering->seats()->count());
    }

    public function test_two_people_cannot_both_be_white(): void
    {
        $gatherings = $this->gatherings();
        $gathering = $gatherings->open(self::CHESS);

        $gatherings->seat($gathering, $this->visitor('alice'), 'white');

        $this->expectException(RuntimeException::class);

        $gatherings->seat($gathering, $this->visitor('bob'), 'white');
    }

    /**
     * Somebody present but not playing. A watch party is all audience, and a
     * chess game has two players and everybody else.
     */
    public function test_several_people_can_be_present_without_a_seat(): void
    {
        $gatherings = $this->gatherings();
        $gathering = $gatherings->open(self::CHESS);

        $gatherings->seat($gathering, $this->visitor('carol'));
        $gatherings->seat($gathering, $this->visitor('dave'));

        $this->assertSame(2, $gathering->seats()->count());
    }

    /**
     * The ticket says what the venue decided, not what the browser asked for.
     */
    public function test_a_ticket_carries_the_seat_the_venue_gave(): void
    {
        $gatherings = $this->gatherings();
        $gathering = $gatherings->open(self::CHESS);
        $alice = $this->visitor();

        $gatherings->seat($gathering, $alice, 'white');

        $claims = json_decode(
            (string) base64_decode(strtr(explode('.', $gatherings->admit($gathering, $alice))[1], '-_', '+/'), true),
            true,
        );

        $this->assertSame('white', $claims['seat']);
        $this->assertSame($gathering->room(), $claims['room']);
        $this->assertSame($alice->did, $claims['sub']);
    }

    public function test_somebody_with_no_place_there_is_given_no_way_in(): void
    {
        $gatherings = $this->gatherings();
        $gathering = $gatherings->open(self::CHESS);

        $gatherings->seat($gathering, $this->visitor('alice'), 'white');

        $this->expectException(RuntimeException::class);

        $gatherings->admit($gathering, $this->visitor('stranger'));
    }

    public function test_nothing_gets_a_way_into_something_that_is_over(): void
    {
        $gatherings = $this->gatherings();
        $gathering = $gatherings->open(self::CHESS);
        $alice = $this->visitor();

        $gatherings->seat($gathering, $alice, 'white');
        $gatherings->conclude($gathering);

        $this->expectException(RuntimeException::class);

        $gatherings->admit($gathering->refresh(), $alice);
    }

    // ── And the endpoint a browser actually calls ────────────────────────────

    public function test_a_visitor_is_handed_a_ticket_and_somewhere_to_take_it(): void
    {
        config()->set('streetmesh.venue.hub', 'wss://hub.games.test');

        $gatherings = $this->gatherings();
        $gathering = $gatherings->open(self::CHESS);
        $alice = $this->visitor();

        $gatherings->seat($gathering, $alice, 'white');

        session([Visitors::SESSION_KEY => $alice->id]);

        $this->post(route('venue.ticket', $gathering->key))
            ->assertOk()
            ->assertJsonPath('room', $gathering->room())
            ->assertJsonPath('hub', 'wss://hub.games.test')
            ->assertJsonPath('experience', self::CHESS);
    }

    public function test_nobody_visiting_is_handed_nothing(): void
    {
        $gathering = $this->gatherings()->open(self::CHESS);

        $this->post(route('venue.ticket', $gathering->key))->assertRedirect(route('venue.connect'));
    }

    /**
     * Being a visitor is not the same as belonging at a particular table.
     */
    public function test_a_visitor_with_no_place_there_is_refused(): void
    {
        $gathering = $this->gatherings()->open(self::CHESS);

        session([Visitors::SESSION_KEY => $this->visitor('stranger')->id]);

        $this->post(route('venue.ticket', $gathering->key))->assertForbidden();
    }

    public function test_a_gathering_nobody_opened_is_not_found(): void
    {
        session([Visitors::SESSION_KEY => $this->visitor()->id]);

        $this->post(route('venue.ticket', 'nothing-by-that-name'))->assertNotFound();
    }
}
