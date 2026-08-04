<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use StreetMesh\Venue\Http\ConnectController;

new #[Title('Connect')] class extends Component
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
    Constrained to the same width as the login form, because this is the same
    kind of screen: one field, one decision, nothing to scan. A form that spans
    a whole page reads as though it wanted more from you than an address.
--}}
<div class="mx-auto flex w-full max-w-sm flex-col gap-6 py-10">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="xl">{{ __('Connect') }}</flux:heading>
        <flux:text>{{ __('There is nothing to sign up for here.') }}</flux:text>
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
            :label="__('Your StreetMesh Address')"
            placeholder="alice.example.com"
            :value="old('handle', $this->mine())"
            autofocus
            autocomplete="username"
            :description="__('We\'ll send you there to log in.')"
        />

        @error(ConnectController::REFUSAL)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.text>{{ $message }}</flux:callout.text>
            </flux:callout>
        @enderror

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Authorize') }}
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
