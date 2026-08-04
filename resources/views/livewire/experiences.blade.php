<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Venue\Experiences\Experience;
use StreetMesh\Venue\Experiences\Experiences;
use StreetMesh\Venue\Visitors;

new #[Title('Experiences')] class extends Component
{
    /**
     * Everything this venue can offer.
     *
     * @return array<int, Experience>
     */
    public function offered(): array
    {
        return app(Experiences::class)->all();
    }

    public function visitor(): ?Delegation
    {
        return app(Visitors::class)->current(request());
    }
};?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Experiences') }}</flux:heading>
        <flux:text>{{ __('What there is to do here.') }}</flux:text>
    </div>

    @if ($this->visitor() !== null)
        {{--
            Who is here, and how to stop being here.

            Their name comes from the permission their own server gave us rather
            than from anything this venue holds — which is why leaving is a
            button rather than an account to delete.
        --}}
        <flux:callout icon="user">
            <flux:callout.heading>{{ $this->visitor()->handle }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Visiting from :server.', ['server' => parse_url($this->visitor()->issuer, PHP_URL_HOST)]) }}
                {{ __('Nothing about you is kept here.') }}
            </flux:callout.text>

            <form method="POST" action="{{ route('venue.leave') }}" class="mt-3">
                @csrf
                <flux:button type="submit" size="sm" variant="ghost">{{ __('Leave') }}</flux:button>
            </form>
        </flux:callout>
    @endif

    @if ($this->offered() === [])
        <flux:callout icon="squares-2x2">
            <flux:callout.heading>{{ __('Nothing installed yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('An experience is a package: chess is one, and a shop would be another. Installing one puts it here.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->offered() as $experience)
                <flux:card class="flex flex-col gap-3">
                    <flux:icon :name="$experience->icon()" class="size-6" />

                    <div class="flex flex-col gap-1">
                        <flux:heading>{{ $experience->title() }}</flux:heading>
                        <flux:text class="text-sm">{{ $experience->description() }}</flux:text>
                    </div>

                    <div class="mt-auto">
                        <flux:button :href="route($experience->route())" size="sm" variant="ghost" wire:navigate>
                            {{ __('Go in') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
