<?php

namespace StreetMesh\Venue;

use Illuminate\Http\Request;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;

/**
 * Who is here, without anybody having an account here.
 *
 * A visitor is not a user. There is no row for them in this server's users
 * table, no password, nothing to reset, nothing to delete when they leave and
 * nothing left behind if they never come back. What there is instead is
 * permission somebody's own server gave us, and their name comes from there.
 *
 * So this is deliberately thin: the session holds which delegation we are
 * acting under, and everything else is looked up from it. Copying their name
 * into the session would make it a second source of truth that drifts the
 * moment they change their handle.
 */
final class Visitors
{
    public const SESSION_KEY = 'streetmesh.visitor';

    /**
     * Where they were heading before they were sent home to be asked.
     *
     * A sibling of the key above rather than a child of it, and that is not
     * cosmetic. Laravel's session reads dots as nesting, so when this was
     * `streetmesh.visitor.intended` writing it turned `streetmesh.visitor` from
     * a delegation id into `['intended' => …]` — silently unseating whoever was
     * here, and then failing with a type error from `find()` several requests
     * later, nowhere near the cause.
     */
    public const INTENDED_KEY = 'streetmesh.intended';

    public function seat(Request $request, Delegation $delegation): void
    {
        /*
         * A new session identifier the moment who-is-here changes, which is the
         * ordinary defence against somebody being handed a session before they
         * arrive and then finding themselves inside somebody else's visit.
         */
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $delegation->id);
    }

    public function current(Request $request): ?Delegation
    {
        $id = $request->session()->get(self::SESSION_KEY);

        /*
         * Anything but a single id means nobody is here. `find()` given an
         * array quietly returns a collection instead of a model, so a session
         * holding something unexpected surfaces as a type error from deep
         * inside Eloquent rather than as "you are not seated" — which is what
         * it actually means.
         */
        return is_int($id) || is_string($id)
            ? Delegation::query()->find($id)
            : null;
    }

    /**
     * Leaving forgets the visit here and keeps the permission, which is not the
     * same as withdrawing it.
     *
     * Taking permission back is done at their own server, because that is the
     * only place it can be done in a way that survives this venue disagreeing.
     * A venue that could revoke on their behalf would be a venue they had to
     * trust to do it.
     */
    public function leave(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();
    }
}
