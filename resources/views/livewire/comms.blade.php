<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Venue\Parties\Invitation;
use StreetMesh\Venue\Parties\Parties;
use StreetMesh\Venue\Parties\Party;
use StreetMesh\Venue\Visitors;

/**
 * Everything to do with talking, on one panel.
 *
 * Two tabs, because there are two conversations and they are different in kind.
 * The room is wherever you happen to be standing and everybody there can read
 * it; the party is who you came with and travels with you between experiences.
 *
 * Which room this is arrives from the page rather than being worked out here.
 * Only an experience knows whether one of its screens is a place people can
 * talk in, and a venue guessing from a URL would put a chat box on a settings
 * page.
 */
new class extends Component
{
    public string $tab = 'room';

    /** The space this screen is, as the experience around us named it. */
    public string $space = '';

    public string $spaceLabel = '';

    public string $joining = '';

    public string $inviting = '';

    /**
     * Whether the reader has picked a tab themselves.
     *
     * Until they do, the panel follows the page: a screen that is a room opens
     * on the room, and one that is not opens on the party, because an empty tab
     * is a worse first thing to see than the wrong one. Once somebody has
     * chosen, walking into a room stops moving the panel out from under them.
     */
    public bool $chosen = false;

    public function mount(): void
    {
        /*
         * No context has arrived yet — it comes from the host document a moment
         * after this renders — so this is the answer for "nowhere in
         * particular", which is what nowhere-yet looks like.
         */
        $this->tab = $this->parties()->enabled() ? 'party' : 'room';
    }

    public function choose(string $tab): void
    {
        $this->tab = $tab;
        $this->chosen = true;
    }

    #[On('comms-context')]
    public function context(string $space = '', string $label = ''): void
    {
        $this->space = $space;
        $this->spaceLabel = $label;

        if (! $this->chosen) {
            $this->tab = $space !== '' ? 'room' : ($this->parties()->enabled() ? 'party' : 'room');
        }

        unset($this->roster, $this->invitations, $this->here);
    }

    private function parties(): Parties
    {
        return app(Parties::class);
    }

    private function visitor(): ?Delegation
    {
        return app(Visitors::class)->current(request());
    }

    #[Computed]
    public function offered(): bool
    {
        return $this->parties()->enabled();
    }

    #[Computed]
    public function party(): ?Party
    {
        return $this->parties()->partyOf($this->visitor());
    }

    /** @return Collection<int, Delegation> */
    #[Computed]
    public function roster(): Collection
    {
        $party = $this->party();

        return $party === null ? new Collection : $this->parties()->rosterOf($party);
    }

    /** @return Collection<int, Invitation> */
    #[Computed]
    public function invitations(): Collection
    {
        return $this->parties()->invitationsFor($this->visitor());
    }

    /** @return Collection<int, Delegation> */
    #[Computed]
    public function here(): Collection
    {
        $visitor = $this->visitor();

        return $visitor === null ? new Collection : $this->parties()->here($visitor);
    }

    #[Computed]
    public function full(): bool
    {
        return $this->roster()->count() >= $this->parties()->size();
    }

    public function start(): void
    {
        $this->run(fn (Delegation $me) => $this->parties()->open($me));
    }

    public function join(): void
    {
        if (trim($this->joining) === '') {
            return;
        }

        $this->run(fn (Delegation $me) => $this->parties()->joinByCode($this->joining, $me));

        $this->joining = '';
    }

    public function invite(): void
    {
        $party = $this->party();

        if ($party === null || $this->inviting === '') {
            return;
        }

        $this->run(fn (Delegation $me) => $this->parties()->invite($party, $me, $this->inviting));

        $this->inviting = '';
    }

    public function accept(int $invitation): void
    {
        $offer = Invitation::find($invitation);

        if ($offer !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->accept($offer, $me));
        }
    }

    public function decline(int $invitation): void
    {
        $offer = Invitation::find($invitation);

        if ($offer !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->decline($offer, $me));
        }
    }

    public function rotate(): void
    {
        $party = $this->party();

        if ($party !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->rotateCode($party, $me));
        }
    }

    public function leave(): void
    {
        $party = $this->party();

        if ($party !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->leave($party, $me));
        }
    }

    /**
     * Do something that may refuse, and say why on the panel if it does.
     *
     * Everything `Parties` throws is a sentence worth reading — the party
     * filled up, the code answers to nobody, you are already in one — so they
     * all arrive the same way.
     *
     * The stage is told afterwards because joining or leaving a party changes
     * which room its media belongs to, and it is a separate document that
     * cannot see this happen.
     */
    private function run(callable $work): void
    {
        $visitor = $this->visitor();

        if ($visitor === null) {
            return;
        }

        try {
            $work($visitor);
        } catch (Throwable $refused) {
            $this->addError('party', $refused->getMessage());
        }

        unset($this->party, $this->roster, $this->invitations, $this->here, $this->full);

        /*
         * Tell the page, which is where the media lives now.
         *
         * Starting, joining or leaving a party changes which room the camera
         * belongs to, and none of it reloads the page — so without this the
         * host would go on holding connections to a party somebody has left,
         * or none to the one they just joined.
         */
        $this->dispatch('party-changed', party: app(\StreetMesh\Venue\Comms::class)
            ->forHost(request())['party']);
    }
};?>

<div class="flex h-full flex-col bg-white dark:bg-zinc-900" wire:poll.5s>
    {{-- The two conversations, and which one is being read. --}}
    <div class="flex shrink-0 border-b border-zinc-200 dark:border-zinc-700">
        <button
            type="button"
            wire:click="choose('room')"
            @class([
                'flex-1 px-4 py-3 text-sm font-medium',
                'border-b-2 border-[var(--sm-accent)] font-semibold text-zinc-900 dark:text-[var(--sm-accent)]' => $tab === 'room',
                'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== 'room',
            ])
        >{{ $spaceLabel !== '' ? $spaceLabel : __('Room') }}</button>

        @if ($this->offered)
            <button
                type="button"
                wire:click="choose('party')"
                @class([
                    'flex-1 px-4 py-3 text-sm font-medium',
                    'border-b-2 border-[var(--sm-accent)] font-semibold text-zinc-900 dark:text-[var(--sm-accent)]' => $tab === 'party',
                    'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== 'party',
                ])
            >
                {{ __('Party') }}
                @if ($this->invitations->isNotEmpty())
                    <span class="ml-1 inline-block size-2 rounded-full bg-red-500 align-middle"></span>
                @endif
            </button>
        @endif

        <button
            type="button"
            class="px-3 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
            x-on:click="window.parent.postMessage({ method: 'streetmesh.panel.close', params: {} }, window.location.origin)"
            aria-label="{{ __('Close') }}"
        >&times;</button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto p-3">
        @if ($tab === 'room')
            @if ($space === '')
                {{-- Nowhere in particular. A venue has screens that are not
                     places — a menu, a settings page — and pretending otherwise
                     would put an unanswerable chat box on them. --}}
                <flux:text>{{ __('You are not anywhere people are talking. Go into an experience and this fills up.') }}</flux:text>
            @else
                @livewire('venue::chat', [
                    'space' => $space,
                    'placeholder' => __('Say something to the room'),
                ], key('room-'.md5($space)))
            @endif
        @else
            @include('venue::comms.party')
        @endif
    </div>

    {{--
        Being heard and seen, on both tabs and whether or not there is a party.

        Not inside the party branch, which is where these started and where they
        were useless: your own circle only appears once something is turned on,
        so with no party and nothing on there was no switch anywhere on screen —
        no way in at all. A camera is yours rather than the party's.

        The switches themselves live in the stage document, because that is
        where the microphone is. These say so; they do not do it.
    --}}
    <div
        class="flex shrink-0 gap-2 border-t border-zinc-200 p-3 dark:border-zinc-700"
        x-data="{ speaking: false, showing: false }"
        x-on:message.window="
            if ($event.data?.method === 'streetmesh.stage.media') {
                speaking = $event.data.params.speaking
                showing = $event.data.params.showing
            }
        "
    >
        <flux:button
            size="sm"
            icon="microphone"
            class="flex-1"
            x-on:click="window.parent.postMessage({ method: 'streetmesh.panel.speak', params: {} }, window.location.origin)"
            ::variant="speaking ? 'primary' : 'filled'"
        >
            <span x-text="speaking ? '{{ __('Speaking') }}' : '{{ __('Speak') }}'"></span>
        </flux:button>

        <flux:button
            size="sm"
            icon="video-camera"
            class="flex-1"
            x-on:click="window.parent.postMessage({ method: 'streetmesh.panel.show', params: {} }, window.location.origin)"
            ::variant="showing ? 'primary' : 'filled'"
        >
            <span x-text="showing ? '{{ __('Showing') }}' : '{{ __('Show') }}'"></span>
        </flux:button>
    </div>
</div>
