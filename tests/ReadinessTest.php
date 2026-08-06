<?php

namespace StreetMesh\Venue\Tests;

use PHPUnit\Framework\TestCase as Plain;
use StreetMesh\Venue\Readiness;

/**
 * What a venue must have before it opens.
 *
 * No application, because there is nothing here that needs one — and because
 * the check this stands in for is skipped in the console, which is where every
 * other test in this package runs. It went unproven for exactly that long.
 */
final class ReadinessTest extends Plain
{
    public function test_a_venue_with_both_opens(): void
    {
        $this->assertNull(
            (new Readiness(isVenue: true, hasSecret: true, hub: 'wss://hub.test'))->missing()
        );
    }

    /**
     * A server built from the same codebase as a venue, which is not one. It
     * installed this package because Composer installed it, and owes nobody an
     * account of a hub it does not have.
     */
    public function test_a_server_that_is_not_a_venue_needs_neither(): void
    {
        $this->assertNull(
            (new Readiness(isVenue: false, hasSecret: false, hub: null))->missing()
        );
    }

    public function test_a_venue_with_no_secret_says_which_one(): void
    {
        $missing = (new Readiness(isVenue: true, hasSecret: false, hub: 'wss://hub.test'))->missing();

        $this->assertNotNull($missing);
        $this->assertStringContainsString('STREETMESH_REALTIME_SECRET', $missing);
    }

    /**
     * The refusal that did not exist before. A venue pointed at no hub used to
     * start perfectly well and offer experiences that could never open.
     */
    public function test_a_venue_with_no_hub_says_which_one(): void
    {
        $missing = (new Readiness(isVenue: true, hasSecret: true, hub: null))->missing();

        $this->assertNotNull($missing);
        $this->assertStringContainsString('STREETMESH_HUB', $missing);
        $this->assertStringContainsString('STREETMESH_VENUE=false', $missing);
    }

    public function test_an_empty_hub_is_no_hub(): void
    {
        $this->assertNotNull(
            (new Readiness(isVenue: true, hasSecret: true, hub: '   '))->missing()
        );
    }

    /**
     * One complaint at a time, and the secret first — it is the one somebody
     * setting a server up hits before they have anything to point at.
     */
    public function test_it_names_the_secret_before_the_hub(): void
    {
        $missing = (new Readiness(isVenue: true, hasSecret: false, hub: null))->missing();

        $this->assertStringContainsString('STREETMESH_REALTIME_SECRET', (string) $missing);
        $this->assertStringNotContainsString('STREETMESH_HUB', (string) $missing);
    }
}
