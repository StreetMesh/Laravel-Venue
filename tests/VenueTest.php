<?php

namespace StreetMesh\Venue\Tests;

use Illuminate\Http\Request;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\P256;
use StreetMesh\Venue\Visitors;

class VenueTest extends TestCase
{
    private function capabilities(): Capabilities
    {
        return $this->app->make(Capabilities::class);
    }

    public function test_installing_it_makes_the_server_say_it_hosts_gatherings(): void
    {
        $this->assertTrue($this->capabilities()->has('venue'));
        $this->assertSame(['venue'], $this->capabilities()->names());
    }

    /**
     * The wire and the interface read one list, so they cannot come to disagree
     * about what this server does.
     */
    public function test_the_did_document_says_so_too(): void
    {
        $document = $this->get('/.well-known/did.json')->assertOk()->json();

        $this->assertSame('StreetMeshVenue', $document['service'][0]['type']);
    }

    public function test_it_offers_a_front_page_without_claiming_the_root(): void
    {
        $this->assertSame('venue::front', $this->capabilities()->get('venue')->frontPage());

        /*
         * Offering is not taking. Installed on its own, this package leaves the
         * root empty — because there is one of it however many capabilities are
         * present, and a package claiming it would win or lose on boot order
         * with nobody deciding.
         */
        $this->get('/')->assertNotFound();
    }

    public function test_it_offers_a_panel_for_a_home_page_it_does_not_own(): void
    {
        $widgets = $this->capabilities()->widgets();

        $this->assertCount(1, $widgets);
        $this->assertSame('venue.experiences', $widgets[0]->name());
    }

    /**
     * Packages get installed and removed. A page must not fail to render
     * because a configuration file still names one that left.
     */
    public function test_an_arrangement_naming_nothing_is_skipped_rather_than_fatal(): void
    {
        $this->assertSame([], $this->capabilities()->widgets(['a.package.that.left']));
        $this->assertCount(1, $this->capabilities()->widgets(['venue.experiences', 'gone']));
    }

    public function test_its_own_screen_is_at_its_own_name(): void
    {
        $this->seated();

        $this->get('/experiences')
            ->assertOk()
            ->assertSee('No experiences installed yet');
    }

    /**
     * The screen is a Livewire component this package ships, not a view the
     * host has to know about.
     *
     * Worth asserting rather than assuming, because Livewire keeps a register
     * of component namespaces separate from Blade's, and a package that
     * registers only the Blade one gets a view that resolves and a component
     * that does not — which is how this was first built.
     */
    public function test_it_ships_its_own_livewire_component(): void
    {
        $this->assertNotNull(
            $this->app->make('livewire.finder')->resolveSingleFileComponentPath('venue::experiences')
        );

        $this->seated();

        $this->get('/experiences')->assertSee('wire:model.live="filter"', escape: false);
    }

    /**
     * A menu of things to do is only useful to somebody who can do them, and
     * everything here is done on somebody else's behalf.
     */
    public function test_the_menu_is_behind_the_door(): void
    {
        $this->get('/experiences')->assertRedirect(route('venue.visit'));
    }

    /**
     * Being asked to identify yourself and then dumped at the entrance is the
     * small rudeness that makes a federated arrival feel worse than a local
     * sign-in.
     */
    public function test_where_somebody_was_heading_survives_being_sent_home_to_be_asked(): void
    {
        $this->get('/experiences');

        $this->assertSame(url('/experiences'), session(Visitors::INTENDED_KEY));
    }

    public function test_the_door_asks_for_an_address_and_offers_no_account(): void
    {
        $this->get('/visit')
            ->assertOk()
            ->assertSee('Your address')
            ->assertSee('There is nothing to sign up for here.')
            ->assertDontSee('Password');
    }

    public function test_arriving_with_nothing_typed_says_so(): void
    {
        $this->post('/visit', ['handle' => '  '])->assertSessionHasErrors('handle');
    }

    /**
     * A name that resolves to nothing is almost always a typo, and should read
     * as one rather than as whatever the discovery chain threw.
     */
    public function test_an_address_that_answers_to_nobody_is_reported_as_an_address(): void
    {
        $this->post('/visit', ['handle' => 'nobody.example'])
            ->assertSessionHasErrors('handle');

        $this->assertStringContainsString(
            'nobody.example',
            (string) session('errors')?->first('handle'),
        );
    }

    /**
     * A callback nobody asked for is somebody else's business, and must not
     * seat them.
     */
    public function test_an_answer_to_a_question_nobody_asked_seats_nobody(): void
    {
        $this->get('/visit/callback?state=made-up&code=made-up')
            ->assertRedirect(route('venue.visit'));

        $this->assertNull(session(Visitors::SESSION_KEY));
    }

    public function test_a_refusal_is_reported_rather_than_left_silent(): void
    {
        $this->get('/visit/callback?error=access_denied')
            ->assertRedirect(route('venue.visit'))
            ->assertSessionHasErrors('handle');
    }

    /**
     * Leaving forgets the visit and keeps the permission, which is not the same
     * as withdrawing it — taking it back is done at their own server, because
     * that is the only place it can be done in a way that survives this venue
     * disagreeing.
     */
    public function test_leaving_forgets_the_visit_here_and_nothing_else(): void
    {
        $delegation = $this->seated();

        $this->post('/leave')->assertRedirect(route('venue.visit'));

        $this->assertNull(session(Visitors::SESSION_KEY));
        $this->assertNotNull($delegation->fresh(), 'the permission itself is theirs to withdraw, not ours');
    }

    /**
     * Somebody is here, and the page says who without this venue holding an
     * account for them.
     */
    public function test_a_seated_visitor_is_named_by_where_they_came_from(): void
    {
        $this->seated();

        $this->get('/experiences')
            ->assertSee('alice.home.test')
            ->assertSee('Nothing about you is kept here.');
    }

    /**
     * The sequence that broke it, kept as a test rather than a memory.
     *
     * Being sent to the door remembers where somebody was heading. When that
     * key was a dot-notation child of the seating key, writing it replaced the
     * delegation id with an array — unseating whoever was here, and then
     * failing several requests later with a type error from inside Eloquent,
     * nowhere near the cause.
     */
    public function test_being_sent_to_the_door_does_not_unseat_whoever_is_here(): void
    {
        $delegation = $this->seated();

        // A guarded page somebody is entitled to, so the middleware lets them
        // through and does not write an intended URL.
        $this->get('/experiences')->assertOk();

        // And one that does write it, on a session that is already seated.
        session([Visitors::INTENDED_KEY => url('/chess')]);

        $this->assertSame($delegation->id, session(Visitors::SESSION_KEY));

        $this->get('/experiences')->assertOk();
    }

    /**
     * A session holding something unexpected means nobody is here, which is
     * what it should say rather than what Eloquent says about arrays.
     */
    public function test_a_session_holding_nonsense_seats_nobody(): void
    {
        $request = Request::create('/experiences');
        $request->setLaravelSession($this->app['session.store']);

        $request->session()->put(Visitors::SESSION_KEY, ['intended' => 'https://example.test']);

        $this->assertNull($this->app->make(Visitors::class)->current($request));
    }

    private function seated(): Delegation
    {
        $delegation = Delegation::create([
            'did' => 'did:web:alice.home.test',
            'handle' => 'alice.home.test',
            'issuer' => 'https://home.test',
            'dpop_key' => Delegation::store(P256::generate()),
            'access_token' => 'a-live-token',
            'scope' => 'atproto',
            'expires_at' => now()->addMinutes(15),
        ]);

        session([Visitors::SESSION_KEY => $delegation->id]);

        return $delegation;
    }
}
