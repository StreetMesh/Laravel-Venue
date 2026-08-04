<?php

namespace StreetMesh\Venue\Experiences;

/**
 * Something a venue hosts.
 *
 * Not a capability, and the distinction is the one this interface exists to
 * make. A capability answers "what kind of server is this" — a domicile, a
 * venue, both — and says so on the wire, in a DID document a stranger reads
 * before deciding to come. An experience answers "what can I do here", which is
 * nobody's business until they have arrived and looked at the menu.
 *
 * Chess was written as a capability first, and it showed: two of the four
 * methods it had to implement returned empty strings, because it had no service
 * type to announce and no front page to greet anybody with. A class that can
 * only half-satisfy an interface is usually implementing the wrong one.
 */
interface Experience
{
    /**
     * The NSID, which is three things at once.
     *
     * It names the collection its records go in, the room type its hub serves,
     * and the experience itself. One name, because they are one thing seen from
     * three sides — and reverse-domain, so two experiences by different authors
     * cannot collide without somebody doing it deliberately.
     */
    public function name(): string;

    /** What it is called on the menu. */
    public function title(): string;

    /**
     * One sentence, for somebody deciding whether to go in.
     */
    public function description(): string;

    /** A Flux icon name, for the gallery. */
    public function icon(): string;

    /** The route its own screen lives at. */
    public function route(): string;

    /**
     * What a visitor has to agree to before this can do its job.
     *
     * Declared here rather than configured, so that installing an experience
     * asks for what it needs. A venue whose configuration and installed
     * packages disagreed would take somebody through a consent screen and then
     * fail to write the record it had just promised them.
     *
     * @return array<int, string> ATProtocol scope strings
     */
    public function scopes(): array;
}
