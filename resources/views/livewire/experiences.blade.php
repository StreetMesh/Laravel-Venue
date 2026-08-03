<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Venue\Visitors;

new #[Title('Experiences')] class extends Component
{
    public string $filter = 'all';

    /**
     * @return array<int, string>
     */
    public function experiences(): array
    {
        return [];
    }

    public function visitor(): ?Delegation
    {
        return app(Visitors::class)->current(request());
    }
};?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Experiences') }}</flux:heading>

        {{-- Interactive, from a package, with no wiring in the host. --}}
        <flux:select wire:model.live="filter" class="max-w-40">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="open">{{ __('Open now') }}</flux:select.option>
        </flux:select>
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

    @forelse ($this->experiences() as $experience)
        <flux:card>{{ $experience }}</flux:card>
    @empty
        <flux:callout icon="squares-2x2">
            <flux:callout.heading>{{ __('No experiences installed yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('An experience is a package: chess is one, and a shop would be another.') }}
            </flux:callout.text>
        </flux:callout>
    @endforelse
</div>
