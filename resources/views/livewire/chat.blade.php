<?php

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use StreetMesh\Venue\Chat\Chat;
use StreetMesh\Venue\Chat\Message;
use StreetMesh\Venue\Visitors;

/**
 * Talking, in a place.
 *
 * One component for every kind of space, because a lobby, a table and a party
 * are the same kind of thing — somewhere you can be. What differs between them
 * is the path handed in, and nothing else.
 *
 * Its own component so that refreshing it cannot disturb whatever is beside it.
 * A board is driven by the room and holds state Livewire knows nothing about,
 * and a conversation that re-rendered the page around it would take the game
 * with it every few seconds.
 *
 * Polled rather than pushed, which is what the prototype did and is not what
 * this should stay as. The server it lands in has Reverb configured, and a
 * space with a broadcast behind it would stop asking a question whose answer is
 * almost always "nothing" — see the note in the decision record.
 */
new class extends Component
{
    public string $space = '';

    public string $placeholder = 'Say something';

    public function mount(string $space, string $placeholder = 'Say something'): void
    {
        $this->space = $space;
        $this->placeholder = $placeholder;
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        if (! $this->readable()) {
            return new Collection;
        }

        return Message::recentlyIn($this->space);
    }

    /**
     * Whether this conversation is any of their business.
     *
     * Asked on every render rather than once at mount. A party is the space
     * where this can change while somebody is looking at it — they leave, or
     * the last other member does and it breaks up — and a panel that went on
     * showing what was said afterwards would be a private conversation left on
     * screen after the door closed.
     */
    #[Computed]
    public function readable(): bool
    {
        return app(Chat::class)->readableBy(
            $this->space,
            app(Visitors::class)->current(request()),
        );
    }

    /**
     * Say something.
     *
     * The message arrives as an argument rather than living in a bound
     * property, and that is what keeps the field usable. This component polls,
     * and every poll morphs its own DOM — a bound input is reset to whatever
     * the server last knew, which mid-sentence means the words vanishing and
     * the caret with them.
     */
    public function say(string $body = ''): void
    {
        $visitor = app(Visitors::class)->current(request());

        if ($visitor === null) {
            return;
        }

        try {
            app(Chat::class)->say($this->space, $visitor, $body);
        } catch (Throwable $refused) {
            $this->addError('saying', $refused->getMessage());

            return;
        }

        unset($this->messages);
    }
};?>

<div class="flex h-full flex-col gap-3" wire:poll.2s>
    <div class="flex-1 overflow-y-auto">
        @forelse ($this->messages as $message)
            <div class="flex flex-col gap-0.5 py-1.5" wire:key="said-{{ $message->id }}">
                <flux:text size="sm" class="font-medium">{{ $message->name }}</flux:text>
                <flux:text>{{ $message->body }}</flux:text>
            </div>
        @empty
            <flux:text class="py-1.5">
                {{ $this->readable ? __('Nobody has said anything yet.') : __('This conversation is not yours.') }}
            </flux:text>
        @endforelse
    </div>

    @if ($this->readable)
        {{--
            One pill, with the way to send it tucked inside.

            A field and a button beside it is two things to look at for one act.
            This is the shape a chat box has settled into everywhere, and it is
            the shape because it reads as a single thing you type into — the
            button is a full stop rather than a second control.

            A textarea rather than an input, so a long message wraps and the
            field grows to meet it instead of scrolling a single line sideways.
        --}}
        <form
            {{--
                Livewire never touches this.

                The component polls, and a morph reaches into whatever it finds
                — including the field somebody is mid-sentence in, resetting it
                to what the server last knew and taking the caret with it. The
                message is held here and handed over when it is sent, so there
                is nothing bound for a re-render to reset.
            --}}
            wire:ignore
            x-data="{
                text: '',

                grow () {
                    const el = this.$refs.box

                    el.style.height = ''

                    if (el.value) {
                        el.style.height = Math.min(el.scrollHeight, 128) + 'px'
                    }
                },

                send () {
                    const body = this.text.trim()

                    if (body === '') {
                        return
                    }

                    /* Cleared before the round trip. Waiting for the server to
                       say it arrived is a field that stays full while somebody
                       is already typing the next thing. */
                    this.text = ''
                    this.$nextTick(() => this.grow())

                    this.$wire.say(body)
                },
            }"
            x-on:submit.prevent="send()"
            class="relative rounded-3xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700"
        >
            <textarea
                x-ref="box"
                x-model="text"
                rows="1"
                x-on:input="grow()"
                {{--
                    Enter sends and shift-enter breaks the line, which is what a
                    chat box does. The other way round is for writing prose, and
                    nobody writes prose across a chessboard.

                    The shift is tested here rather than left to a modifier on
                    the binding: a plain `.enter` fires whether or not shift is
                    held, so a second binding for the other case does not stop
                    the first — it just sends the message and eats the newline.
                --}}
                x-on:keydown.enter="
                    if (! $event.shiftKey) {
                        $event.preventDefault()
                        send()
                    }
                "
                placeholder="{{ __($placeholder) }}"
                maxlength="{{ \StreetMesh\Venue\Chat\Message::LONGEST }}"
                autocomplete="off"
                class="block max-h-32 w-full resize-none overflow-y-auto rounded-3xl bg-transparent py-3 pl-4 pr-12 text-sm leading-5 outline-none placeholder:text-zinc-400 dark:text-zinc-100 dark:placeholder:text-zinc-500"
            ></textarea>

            <button
                type="submit"
                x-bind:disabled="! text.trim()"
                title="{{ __('Send') }}"
                class="absolute bottom-2 right-2 flex size-7 items-center justify-center rounded-full bg-zinc-900 text-white transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-7 7m7-7l7 7" />
                </svg>

                <span class="sr-only">{{ __('Send') }}</span>
            </button>
        </form>
    @endif
</div>
