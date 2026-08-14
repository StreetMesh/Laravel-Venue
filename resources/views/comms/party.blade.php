{{--
    The party tab.

    Two ways in and one way out. Being asked is the strict one — somebody
    already inside points at a name they can see — and the code is the loose
    one, for saying across a table. The code can be forwarded and the party
    cannot tell how anybody came by it, which is the price of being able to say
    "join with 4F2K" out loud.
--}}
<div class="flex flex-col gap-4">
    @error('party')
        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror

    @if (! $this->offered)
        <flux:text>{{ __('This venue does not do parties.') }}</flux:text>
    @else
        {{-- Somebody is knocking. Answered before anything else, because it is
             the part about to change what the rest of this says. --}}
        @foreach ($this->invitations as $invitation)
            <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="asked-{{ $invitation->id }}">
                <flux:text>{{ __(':who asked you into their party.', ['who' => $invitation->invited_by_name]) }}</flux:text>

                <div class="flex gap-2">
                    <flux:button size="sm" variant="primary" wire:click="accept({{ $invitation->id }})">{{ __('Join them') }}</flux:button>
                    <flux:button size="sm" variant="subtle" wire:click="decline({{ $invitation->id }})">{{ __('No thanks') }}</flux:button>
                </div>
            </div>
        @endforeach

        @if ($this->party === null)
            <flux:button icon="user-group" wire:click="start">{{ __('Start a party') }}</flux:button>

            <flux:separator text="{{ __('or') }}" />

            <form wire:submit="join" class="flex gap-2">
                <flux:input
                    wire:model="joining"
                    :placeholder="__('Code')"
                    autocomplete="off"
                    maxlength="8"
                    class="flex-1 uppercase"
                />
                <flux:button type="submit">{{ __('Join') }}</flux:button>
            </form>
        @else
            {{-- Who is here. The faces are beside the badge; this is the list
                 you read rather than glance at. --}}
            <div class="flex flex-col gap-1">
                @foreach ($this->roster as $member)
                    <div wire:key="with-{{ $member->did }}">
                        <flux:text>{{ $member->handle }}</flux:text>
                    </div>
                @endforeach
            </div>

            @if ($this->full)
                <flux:text size="sm">{{ __('This party is as big as it can be.') }}</flux:text>
            @else
                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text size="sm">{{ __('Anybody can join with this word:') }}</flux:text>

                    <div class="flex items-center gap-2">
                        {{-- A bare span inherits the document's default ink, which is black in both
                             themes — the one thing on this panel meant to be read out was the
                             one thing that disappeared in the dark. --}}
                        <span class="font-mono text-lg tracking-widest text-zinc-900 dark:text-white">{{ $this->party->code }}</span>

                        <flux:button size="xs" variant="subtle" wire:click="rotate">{{ __('New word') }}</flux:button>
                    </div>

                    @if ($this->here->isNotEmpty())
                        <form wire:submit="invite" class="flex gap-2">
                            <flux:select wire:model="inviting" size="sm" class="flex-1">
                                <flux:select.option value="">{{ __('Or ask somebody here…') }}</flux:select.option>

                                @foreach ($this->here as $person)
                                    <flux:select.option value="{{ $person->did }}">{{ $person->handle }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:button size="sm" type="submit">{{ __('Ask') }}</flux:button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Text layers rather than superseding: somebody cut off from the
                 room's chat would miss whatever everybody around them is
                 reacting to. Voice is the one that replaces. --}}
            @livewire('venue::chat', [
                'space' => $this->party->room(),
                'placeholder' => __('Say something to your party'),
            ], key('party-'.$this->party->key))

            <flux:button size="sm" variant="subtle" wire:click="leave">{{ __('Leave the party') }}</flux:button>
        @endif
    @endif
</div>
