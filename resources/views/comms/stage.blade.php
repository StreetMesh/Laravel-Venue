<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Cameras') }}</title>

    {{--
        A shell, and nothing else.

        There is no script here. The camera, the microphone and the peer
        connections live in the page that holds this frame, because a
        navigation reloads every iframe on it and a stream cannot outlive the
        document that acquired it. What is left here is the stylesheet that
        keeps those faces out of the venue's CSS, and somewhere to put them.
    --}}

    <style>
        /*
            No `color-scheme` here, deliberately.

            Declaring one makes the browser paint its own canvas behind the
            document — opaque, and in the theme's colour. These frames are
            transparent so that a circle reads as a circle, and setting
            `color-scheme: dark` put a dark rectangle behind the badge and every
            face. The colours below follow the class the page sets instead.
        */

        /*
            These documents load no stylesheet of their own, which is what keeps
            them fast — and means they get none of the reset the rest of the
            application has. Without this the browser default of `content-box`
            applies: padding is added to a width rather than counted inside it,
            so a container at `height: 100%` with padding below it is taller
            than the frame holding it and hangs its contents out of the bottom.
        */
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            height: 100%;
            background: transparent;
            overflow: hidden;
        }

        #stage {
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            height: 100%;

            /*
                Lifted onto the same line as the badge.

                The badge's circle is centred in a frame slightly larger than
                itself, so it floats half that padding above the bottom of its
                own iframe. These faces sit flush in a frame of their own, and
                without this they line up with nothing.
            */
            padding-bottom: {{ $lift }}px;
        }

        .face {
            position: relative;
            width: {{ $badge }}px;
            height: {{ $badge }}px;
            flex: 0 0 {{ $badge }}px;
            border-radius: 9999px;
            overflow: hidden;
            background: #27272a;

            /* The same room the badge has around itself, so one face occupies
               exactly one badge's worth of the row — and so the shadow the host
               draws has somewhere to fall. */
            margin-left: {{ $pad }}px;
        }

        .face video,
        .face .avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /*
            Your own camera is mirrored; everybody else's is not.

            A camera shows what it sees, which is you as other people see you —
            and that reads as backwards, because the only place anybody watches
            themselves move is a mirror. Raising a hand and watching the wrong
            one go up is the tell.

            Never applied to the others: what arrives from them is already the
            right way round, and flipping it would put their writing backwards.
        */
        .face.self video { transform: scaleX(-1); }

        .face video[hidden],
        .face .avatar[hidden],
        .face .quiet[hidden] { display: none; }

        .face .avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            color: {{ $palette['paper'] }};
            font: 600 {{ round($badge * 0.38) }}px/1 ui-sans-serif, system-ui, sans-serif;
            background: linear-gradient(160deg, #3f3f46, {{ $palette['ink'] }});
        }

        /*
            Muted is drawn over the face rather than instead of it. Somebody who
            has their camera on and their microphone off is still there to look
            at, and replacing them with an icon would say they had gone.
        */
        .face .quiet {
            position: absolute;
            inset: auto 0 0 0;
            height: {{ round($badge * 0.42) }}px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .6);
            color: #fca5a5;
        }

        .face .quiet svg { width: {{ round($badge * 0.22) }}px; height: {{ round($badge * 0.22) }}px; }
    </style>
</head>
<body>
    <div id="stage"></div>

</body>
</html>
