<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Venue\Http\VisitController;

new #[Title('Arrive')] class extends Component
{
    public string $handle = '';

    /**
     * @return array<int, string>
     */
    public function asking(): array
    {
        return VisitController::asking();
    }
};?>

{{--
    Constrained to the same width as the login form, because this is the same
    kind of screen: one field, one decision, nothing to scan. A form that spans
    a whole page reads as though it wanted more from you than an address.
--}}
<div class="mx-auto flex w-full max-w-sm flex-col gap-6 py-10">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="xl">{{ __('Arrive') }}</flux:heading>
        <flux:text>{{ __('There is nothing to sign up for here.') }}</flux:text>
    </div>

    {{--
        A plain form post rather than a Livewire action, because what happens
        next is a redirect to somebody else's server. Livewire would have to be
        told to do that, and a form already knows how.
    --}}
    <form method="POST" action="{{ route('venue.visit.start') }}" class="flex flex-col gap-4">
        @csrf

        <flux:input
            name="handle"
            :label="__('Your StreetMesh Address')"
            placeholder="alice.example.com"
            :value="old('handle')"
            autofocus
            autocomplete="username"
            :description="__('We\'ll send you there to log in.')"
        />

        @error('handle')
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
