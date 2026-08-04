<?php

namespace StreetMesh\Venue\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use StreetMesh\Protocol\Laravel\Permissions\Delegations;
use StreetMesh\Protocol\Scope;
use StreetMesh\Venue\Experiences\Experiences;
use StreetMesh\Venue\Visitors;
use Throwable;

/**
 * The door.
 *
 * Somebody arrives having never been here, types a name issued somewhere else,
 * and is sent to their own server to be asked. They come back holding nothing
 * this venue gave them — the permission belongs to them, granted by the server
 * they chose, and can be taken back there without asking us.
 *
 * There is no account to create and no password to invent, which is the whole
 * point rather than a convenience.
 */
final class ConnectController
{
    public function __construct(
        private readonly Delegations $delegations,
        private readonly Visitors $visitors,
        private readonly Experiences $experiences,
    ) {}

    public function start(Request $request): RedirectResponse
    {
        $handle = strtolower(ltrim(trim((string) $request->input('handle')), '@'));

        if ($handle === '') {
            throw ValidationException::withMessages(['handle' => __('Type the address you use.')]);
        }

        try {
            $begun = $this->delegations->begin(
                $handle,

                /*
                 * What is installed, rather than what is configured. A venue
                 * whose configuration and packages disagreed would take
                 * somebody through a consent screen and then fail to write the
                 * record it had just promised them.
                 */
                $this->experiences->scopes((array) config('streetmesh.venue.scopes', [])),

                route('venue.callback'),
            );
        } catch (Throwable $failed) {
            /*
             * Almost always a name that does not resolve, and almost always a
             * typo. Said as a sentence about their address rather than as
             * whatever the discovery chain threw, which names documents nobody
             * outside this project has heard of.
             */
            report($failed);

            throw ValidationException::withMessages([
                'handle' => __('Nothing at :handle answers as a StreetMesh address.', ['handle' => $handle]),
            ]);
        }

        return redirect()->away($begun['url']);
    }

    /**
     * They are back, with an answer.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            /*
             * A refusal is an answer. Saying so beats leaving somebody at a
             * door wondering whether it worked.
             */
            return redirect()->route('venue.connect')->withErrors([
                'handle' => __('Your server did not give permission.'),
            ]);
        }

        try {
            $delegation = $this->delegations->complete(
                (string) $request->query('state'),
                (string) $request->query('code'),
                route('venue.callback'),
            );
        } catch (Throwable $failed) {
            report($failed);

            return redirect()->route('venue.connect')->withErrors([
                'handle' => __('That answer could not be used. Please try arriving again.'),
            ]);
        }

        $this->visitors->seat($request, $delegation);

        $intended = $request->session()->pull(Visitors::INTENDED_KEY);

        return redirect()->to(is_string($intended) ? $intended : route('venue.experiences'));
    }

    public function leave(Request $request): RedirectResponse
    {
        $this->visitors->leave($request);

        return redirect()->route('venue.connect');
    }

    /**
     * What this venue will ask for, as it will be written on their screen.
     *
     * Shown at the door as well as on their own server's consent screen,
     * because somebody deciding whether to type their address here deserves to
     * know what typing it leads to.
     *
     * @return array<int, string>
     */
    public static function asking(): array
    {
        $scopes = app(Experiences::class)->scopes((array) config('streetmesh.venue.scopes', []));

        return array_values(array_filter(array_map(
            function (string $scope): ?string {
                $repo = Scope::parse($scope);

                if ($repo === null) {
                    return null;
                }

                return $repo->actions === [Scope::CREATE]
                    ? __('add :what to your own records, and never change them', [
                        'what' => implode(__(' and '), $repo->collections),
                    ])
                    : __('add, change and remove :what in your own records', [
                        'what' => implode(__(' and '), $repo->collections),
                    ]);
            },
            $scopes,
        )));
    }
}
