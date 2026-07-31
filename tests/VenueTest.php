<?php

namespace StreetMesh\Venue\Tests;

use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;

class VenueTest extends TestCase
{
    public function test_installing_it_makes_the_server_say_it_hosts_gatherings(): void
    {
        $capabilities = $this->app->make(Capabilities::class);

        $this->assertTrue($capabilities->has('venue'));
        $this->assertSame(['venue'], $capabilities->names());
    }

    public function test_the_did_document_says_so_too(): void
    {
        $document = $this->get('/.well-known/did.json')->assertOk()->json();

        // No ATProtocol equivalent, because a place people visit to do things
        // together is what StreetMesh adds rather than adopts.
        $this->assertSame('StreetMeshVenue', $document['service'][0]['type']);
    }

    public function test_it_serves_a_lobby(): void
    {
        $this->get('/')->assertOk()->assertSee('A venue on StreetMesh');
    }
}
