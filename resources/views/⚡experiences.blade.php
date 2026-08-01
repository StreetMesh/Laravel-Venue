<?php

use Livewire\Component;

/**
 * A screen a package brought with it.
 *
 * The mechanism worth proving: a package registers a view namespace, and
 * Livewire resolves single-file components through it exactly as it does for
 * the application's own. Without that, every experience package would have to
 * publish its views into the host — which makes upgrading a package somebody
 * else's merge conflict.
 */
new class extends Component
{
    public string $filter = 'all';

    public function with(): array
    {
        return [
            'experiences' => [],
        ];
    }
}; ?>

<x-layouts::app :title="__('Experiences')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="xl">{{ __('Experiences') }}</flux:heading>

            {{-- Interactive, from a package, with no wiring in the host. --}}
            <flux:select wire:model.live="filter" class="max-w-40">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="open">{{ __('Open now') }}</flux:select.option>
            </flux:select>
        </div>

        @forelse ($experiences as $experience)
            <flux:card>{{ $experience }}</flux:card>
        @empty
            <flux:callout icon="squares-2x2">
                <flux:callout.heading>{{ __('No experiences installed yet') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('An experience is a package: chess is one, and a shop would be another.') }}
                    <span class="font-mono text-xs">{{ $filter }}</span>
                </flux:callout.text>
            </flux:callout>
        @endforelse
    </div>
</x-layouts::app>
