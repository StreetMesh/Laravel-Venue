<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use StreetMesh\Venue\Http\ConnectController;

/*
 * A door, not a page inside the building.
 *
 * The host's auth layout: no sidebar, no navigation, nothing behind it — the
 * same frame its own login screen uses, because this is the same kind of
 * moment. It used to render into the application shell and compensate with a
 * narrow column, which meant somebody who had not arrived yet was shown the
 * furniture of a place they were not in.
 *
 * Named rather than assumed. A package cannot draw its own chrome, and saying
 * which frame it wants is the contract — see the stub in tests/fixtures.
 */
new #[Layout('layouts::auth')] #[Title('Connect')] class extends Component
{
    public string $handle = '';

    /**
     * @return array<int, string>
     */
    public function asking(): array
    {
        return ConnectController::asking();
    }

    /**
     * The address of whoever is signed in here, or none.
     *
     * A server can be both a domicile and a venue, and when it is, the person
     * at this form usually already lives here — asking them to type an address
     * this server issued them is asking a question we can answer. It stays a
     * text field, though, because living here is no reason to be unable to
     * arrive as somebody else.
     *
     * Empty on a venue-only server, where nobody is ever signed in.
     */
    public function mine(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return '';
        }

        return (string) (app(Identities::class)->forUser($user)?->handle ?? '');
    }
};?>

{{--
    The layout centres this and gives it a width, the way it does for a login
    form. One field, one decision, nothing to scan.
--}}
<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="xl">{{ __('Connect') }}</flux:heading>
        <flux:text>{{ __('Sign in with the address your own server gave you. There is no account to make here.') }}</flux:text>
    </div>

    {{--
        A plain form post rather than a Livewire action, because what happens
        next is a redirect to somebody else's server. Livewire would have to be
        told to do that, and a form already knows how.
    --}}
    <form method="POST" action="{{ route('venue.connect.start') }}" class="flex flex-col gap-4">
        @csrf

        <flux:input
            name="handle"
            :label="__('Your address')"
            placeholder="alice.example.com"
            :value="old('handle', $this->mine())"
            autofocus
            autocomplete="username"
            autocapitalize="off"
            spellcheck="false"
            :description="__('We will send you to that server to ask.')"
        />

        @error(ConnectController::REFUSAL)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.text>{{ $message }}</flux:callout.text>
            </flux:callout>
        @enderror

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Continue') }}
        </flux:button>
    </form>

    @if ($this->asking() !== [])
        {{--
            Said here as well as on their own server's consent screen. Somebody
            deciding whether to type their address deserves to know what typing
            it leads to, before they leave this page.
        --}}
        <flux:callout icon="key">
            <flux:callout.heading>{{ __('This venue will ask permission to') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc ps-4">
                    @foreach ($this->asking() as $sentence)
                        <li>{{ $sentence }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @endif
</div>
