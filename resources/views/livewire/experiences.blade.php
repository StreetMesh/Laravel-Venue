<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Venue\Experiences\Experience;
use StreetMesh\Venue\Experiences\Experiences;

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
};?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Experiences') }}</flux:heading>
        <flux:text>{{ __('What there is to do here.') }}</flux:text>
    </div>

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
                            {{ $experience->action() ?? __('Launch') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
