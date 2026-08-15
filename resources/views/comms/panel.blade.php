<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Talking') }}</title>

    {{--
        This one does load the application's assets, and it is the only surface
        that should. The badge is a circle and the stage is a row of video
        elements; this is chat, which means Livewire components the venue
        already ships and the chrome they are written against.
    --}}
    @vite($assets)

    @livewireStyles

    <style>
        /*
            The venue's own colours, as custom properties.

            Published rather than written into classes because Tailwind reads
            arbitrary values out of the source at build time: `text-[#00FF99]`
            is a string it can find, and one built from a PHP variable is not
            there at all when the stylesheet is generated. A `var()` is static,
            so the class survives and the value stays configurable.
        */
        :root {
            --sm-ink: {{ $palette['ink'] }};
            --sm-paper: {{ $palette['paper'] }};
            --sm-accent: {{ $palette['accent'] }};
        }

        html, body { height: 100%; margin: 0; background: transparent; }

        /* The popover's own edge. The host cannot round an iframe's corners
           for it — a border-radius on the element clips nothing inside it in
           every browser — so the document draws its own. */
        body {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, .28);
        }

    </style>
</head>
<body class="h-full">
    @if (! $here)
        {{-- A passer-by. They can watch a public game without ever naming their
             own server, so the answer is an invitation rather than an empty
             panel or a form they did not ask for. --}}
        <div class="flex h-full flex-col items-center justify-center gap-3 bg-white p-6 text-center dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Talking needs a name') }}</flux:heading>
            <flux:text>{{ __('Come through the door with an address your own server issued, and you can talk to the people here.') }}</flux:text>
            <flux:button variant="primary" href="{{ route('venue.connect') }}" target="_top">{{ __('Arrive') }}</flux:button>
        </div>
    @else
        <livewire:venue::comms />
    @endif

    @livewireScripts

    {{-- A poll that cannot reach the server, answered the same way here as on
         every other page. This one runs for as long as the browser is open, so
         it meets that case more than anything else does. --}}
    @include('streetmesh::unreachable')

    <script>
        (function () {
            const tell = (method, params = {}) =>
                window.parent.postMessage({ method, params }, window.location.origin)

            /*
             * Where this screen is, held until there is something able to act
             * on it.
             *
             * The page may say so before Livewire has finished starting, and a
             * dispatch into a Livewire that is not listening yet goes nowhere
             * quietly — which reads as the room tab simply never filling in.
             */
            let latest = null

            const context = (params) => {
                latest = params

                window.Livewire?.dispatch('comms-context', {
                    space: params?.space ?? '',
                    label: params?.label ?? '',
                })
            }

            document.addEventListener('livewire:initialized', () => {
                if (latest) {
                    context(latest)
                }

                /* And ask again, in case the answer arrived before we could
                   have used it. */
                tell('streetmesh.surface.ready')
            })

            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) {
                    return
                }

                const { method, params } = event.data || {}

                /*
                 * Which space the page is. It arrives from the host document,
                 * where the experience declared it, and is handed to the
                 * component — the panel is a separate document and has no other
                 * way to learn where the reader is standing.
                 */
                if (method === 'streetmesh.widget.context') {
                    context(params)
                }
            })

            /*
             * A party started, was joined or was left. The page holds the
             * camera and cannot see any of that happen inside this frame, so it
             * is told — and it is told the addresses too, because it is the
             * thing that will be using them.
             */
            document.addEventListener('livewire:init', () => {
                window.Livewire.on('party-changed', (payload) => {
                    tell('streetmesh.panel.party', {
                        party: (Array.isArray(payload) ? payload[0] : payload)?.party ?? null,
                    })
                })
            })

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    tell('streetmesh.panel.close')
                }
            })

            tell('streetmesh.surface.ready')
        })()
    </script>
</body>
</html>
