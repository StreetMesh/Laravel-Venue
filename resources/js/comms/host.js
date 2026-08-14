import devices from '../media/devices.js'
import mesh from './mesh.js'

/**
 * The comms widget, and everything it is holding.
 *
 * This runs in the host document, and that is the whole point of it. A
 * navigation replaces the page's body and reloads every iframe in it — an
 * iframe reloads whenever it is moved in the DOM, which is what preserving one
 * across a swap amounts to — so nothing that must survive a navigation can live
 * inside a frame. The top-level document is the one context that does survive.
 *
 * So the camera, the microphone and the peer connections live here, and the
 * frames are views over them:
 *
 *   badge   the circle in the corner
 *   panel   what opens when it is pressed — chat, parties, the switches
 *   stage   the row of faces, drawn into by this document
 *
 * The frames are still frames because that is what keeps a venue's screens and
 * this widget out of each other's stylesheets and stacking contexts. They just
 * no longer own anything: the stage is a shell this writes into, which it may
 * do because they are the same origin. Streams cannot be posted between
 * documents — a MediaStream is not structured-cloneable — but they can be
 * handed straight to an element in a document you can reach.
 */
!function () {
    const badge = document.getElementById('streetmesh-badge')
    const panel = document.getElementById('streetmesh-panel')
    const stage = document.getElementById('streetmesh-stage')

    const config = window.streetmeshComms

    if (!config || !badge || !panel || !stage) {
        return
    }

    /*
     * Module scripts run once per URL however many times the page is swapped,
     * so this is the first and only pass. What does re-run is the small inline
     * script that writes `window.streetmeshComms` — which is how a navigation
     * tells us the party may have changed. See `livewire:navigated` below.
     */
    if (window.__streetmeshCommsWired) {
        return
    }

    window.__streetmeshCommsWired = true

    let open = false
    let speaking = false
    let showing = false

    /**
     * What was being shared before this page existed.
     *
     * A navigation keeps this document alive, so the stream survives and none
     * of this is needed. A reload does not, and this is what carries the
     * decision across it — the camera is picked up again on the other side.
     *
     * Session storage rather than local, deliberately. Carrying on across a
     * reload is continuing something switched on a moment ago; carrying on into
     * a visit tomorrow would be a camera coming on by itself.
     */
    const REMEMBERED = 'streetmesh:comms:sharing'

    const remember = () => {
        try {
            window.sessionStorage?.setItem(REMEMBERED, JSON.stringify({ speaking, showing }))
        } catch {
            /* Private browsing refuses rather than answering. It only means the
               camera does not follow you through a reload. */
        }
    }

    const remembered = () => {
        try {
            return JSON.parse(window.sessionStorage?.getItem(REMEMBERED) || '{}') || {}
        } catch {
            return {}
        }
    }

    /**
     * Put this application's theme on each frame's own document.
     *
     * Dark is a class on `<html>` here rather than a preference the browser
     * reports — the venue has a light/dark/system setting of its own — and each
     * frame is a separate document with its own `<html>`. Left alone they
     * follow the operating system, so a venue set to dark had a chat panel in
     * light sitting on top of it.
     *
     * Reached into rather than messaged, because they are the same origin and
     * this is one class. It also means the shells need no script at all.
     */
    const dressFrames = () => {
        const dark = document.documentElement.classList.contains('dark')

        for (const surface of [badge, panel, stage]) {
            surface.contentDocument?.documentElement.classList.toggle('dark', dark)
        }
    }

    for (const surface of [badge, panel, stage]) {
        surface.addEventListener('load', dressFrames)
    }

    /* The venue's own appearance setting changes it while the page is open. */
    new MutationObserver(dressFrames).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    })

    const toAll = (method, params = {}) => {
        for (const surface of [badge, panel, stage]) {
            surface.contentWindow?.postMessage({ method, params }, window.location.origin)
        }
    }

    // ── the media ────────────────────────────────────────────────────────────

    const capture = devices({
        onChange () {
            speaking = capture.holds('audio')
            showing = capture.holds('video')

            party?.carry()
            draw()
            remember()

            /* So the panel's switches show what is actually happening. */
            toAll('streetmesh.stage.media', { speaking, showing })
        },
    })

    let party = null
    let partyKey = null

    /**
     * Join the party the page says we are in, or leave the one we were in.
     *
     * Asked at boot, after every navigation, and whenever the panel says
     * somebody started, joined or left one — the three ways it can change
     * without this document being replaced.
     */
    const reconcileParty = () => {
        const next = config.party ?? null

        if ((next?.key ?? null) === partyKey) {
            return
        }

        party?.leave()
        party = null
        partyKey = next?.key ?? null

        if (next) {
            party = mesh({
                ticketUrl: next.ticketUrl,
                signalsUrl: next.signalsUrl,
                csrf: config.csrf,
                tracks: () => capture.tracks(),
                onPeople: draw,
                onTrouble: (why) => toAll('streetmesh.stage.trouble', { why }),
            })

            void party.join()
        }

        draw()
    }

    /**
     * Turn a camera or a microphone on, or off.
     *
     * A function rather than something drawn, because there is a state where
     * nothing is drawn: somebody with no party and nothing turned on has no
     * circle at all, and that is exactly when they need to turn one on.
     */
    const toggle = (kind) => {
        if (capture.holds(kind)) {
            capture.drop(kind)

            return
        }

        void capture.add(kind).then((got) => {
            if (!got) {
                toAll('streetmesh.stage.trouble', {
                    why: kind === 'audio'
                        ? 'This browser would not give us the microphone.'
                        : 'This browser would not give us the camera.',
                })
            }
        })
    }

    // ── the faces, drawn into the stage's document ───────────────────────────

    /**
     * Circles are kept between redraws but not between loads: they belong to
     * the stage's document, and a navigation gives us a new one. Assigning a
     * stream to a fresh video element restarts playback, so within a document
     * they are reused.
     */
    let circles = new Map()

    const circle = (doc, key) => {
        if (circles.has(key)) {
            return circles.get(key)
        }

        const el = doc.createElement('div')

        el.className = 'face'
        el.innerHTML = `
            <video autoplay playsinline></video>
            <div class="avatar"></div>
            <div class="quiet">${config.icons?.microphoneSlash ?? ''}</div>
        `

        circles.set(key, el)

        return el
    }

    const paint = (el, { name, stream, video, audio, self }) => {
        const picture = el.querySelector('video')
        const avatar = el.querySelector('.avatar')

        if (stream && picture.srcObject !== stream) {
            picture.srcObject = stream
        }

        /* Hearing yourself a fraction of a second late is the single most
           disorienting thing a call can do. */
        picture.muted = Boolean(self)

        picture.hidden = !video
        avatar.hidden = Boolean(video)
        avatar.textContent = (name || '?').replace(/^@/, '').charAt(0).toUpperCase()

        /*
         * Presence comes from the tracks rather than from a report of them.
         * Whether somebody can be heard is answered by whether their audio is
         * arriving, which is the truth rather than a claim about it.
         */
        el.querySelector('.quiet').hidden = audio

        /* Only your own picture is mirrored — see the stage's stylesheet. */
        el.classList.toggle('self', Boolean(self))

        /* A list of who is in the party belongs on the panel, where there is
           room for it. This is what a pointer resting on a circle can find. */
        el.title = name || ''
    }

    function draw () {
        const others = party ? party.people() : []
        const mine = speaking || showing || Boolean(config.party)
        const count = others.length + (mine ? 1 : 0)

        /*
         * Named rather than cleared. `display = ''` removes the inline value
         * and falls back to the stylesheet, which says `none` — so the strip
         * stayed hidden however many faces were on it.
         */
        stage.style.width = count > 0 ? ((config.frame || 90) * count) + 'px' : '0'
        stage.style.display = count > 0 ? 'block' : 'none'

        const doc = stage.contentDocument
        const strip = doc?.getElementById('stage')

        /* The frame is still loading. Its `load` handler draws again. */
        if (!strip) {
            return
        }

        strip.replaceChildren()

        /* Furthest from the badge first: the group, then you. */
        for (const person of others) {
            const el = circle(doc, person.session)

            paint(el, { ...person, self: false })
            strip.appendChild(el)
        }

        if (mine) {
            const el = circle(doc, 'me')

            paint(el, {
                name: config.me,
                stream: capture.stream(),
                video: showing,
                audio: speaking,
                self: true,
            })

            strip.appendChild(el)
        }
    }

    /*
     * A navigation reloads this frame even though the page did not. Its
     * document is new, so the circles in the old one are gone — they are
     * rebuilt against streams that never stopped.
     */
    stage.addEventListener('load', () => {
        circles = new Map()
        draw()
    })

    /*
     * And every frame is told where it is the moment it can hear.
     *
     * This script is a module, so it is deferred and runs after the page has
     * parsed — by which time a frame may already have loaded and announced
     * itself to nobody. Waiting to be asked meant the panel never learned which
     * room it was in and offered an empty tab forever.
     */
    panel.addEventListener('load', () => sendContext())

    // ── the panel and the badge ──────────────────────────────────────────────

    const setOpen = (next) => {
        open = next
        panel.style.display = open ? 'block' : 'none'
        toAll('streetmesh.widget.toggle', { open })
    }

    /**
     * Stand the placeholder down once the badge has drawn itself.
     *
     * A circle painted on the frame keeps the corner from being empty while the
     * document inside it loads. It must then get out of the way: two circles
     * drawn one over the other show as a ring wherever their edges disagree.
     */
    const standDown = () => {
        badge.style.background = 'transparent'
    }

    badge.addEventListener('load', standDown)

    if (badge.contentDocument?.readyState === 'complete') {
        standDown()
    }

    /**
     * Which space this screen is, read from the page rather than remembered.
     *
     * An experience marks its own screen, and the mark is swapped with the rest
     * of the body on a navigation — so reading it is always the current answer.
     */
    const readContext = () => {
        const marked = document.querySelector('[data-streetmesh-space]')

        return marked
            ? { space: marked.dataset.streetmeshSpace || '', label: marked.dataset.streetmeshLabel || '' }
            : { space: '', label: '' }
    }

    let context = { ...readContext(), ...(config.context || {}) }

    const sendContext = () => {
        toAll('streetmesh.widget.context', context)
        party?.here(context.space)
    }

    Object.assign(config, {
        open: () => setOpen(true),
        close: () => setOpen(false),
        toggle: () => setOpen(!open),

        /** For a screen whose space is not known until something happens on it. */
        context (next) {
            context = { ...context, ...next }
            sendContext()
        },
    })

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) {
            return
        }

        const { method, params } = event.data || {}

        if (!method || method.indexOf('streetmesh.') !== 0) {
            return
        }

        if (method === 'streetmesh.badge.click') {
            setOpen(!open)
        } else if (method === 'streetmesh.panel.close' || method === 'streetmesh.badge.esc') {
            setOpen(false)
        } else if (method === 'streetmesh.panel.speak') {
            toggle('audio')
        } else if (method === 'streetmesh.panel.show') {
            toggle('video')
        } else if (method === 'streetmesh.panel.party') {
            /*
             * Somebody started, joined or left one. The page did not reload, so
             * this is the only way this document hears about it.
             */
            config.party = params?.party ?? null
            reconcileParty()
        } else if (method === 'streetmesh.surface.ready') {
            sendContext()
            toAll('streetmesh.stage.media', { speaking, showing })
        } else {
            /* One surface talking to the others, carried without being read. */
            toAll(method, params)
        }
    })

    /*
     * A navigation swaps the body — and with it the mark an experience left and
     * the inline script that says which party this visitor is in. The frames
     * are reloaded; the media is not.
     */
    document.addEventListener('livewire:navigated', () => {
        context = readContext()
        reconcileParty()
        sendContext()
    })

    // ── and go ───────────────────────────────────────────────────────────────

    reconcileParty()
    sendContext()
    dressFrames()

    /**
     * Pick up whatever was being shared before a reload.
     *
     * Silent, because the permission was already given to this origin — the
     * browser asks once and remembers. What it may not be is a *first* request:
     * with nothing remembered nothing is asked for.
     *
     * One kind at a time: asking for a camera while already holding a
     * microphone ends the microphone's track on WebKit.
     */
    void (async () => {
        const held = remembered()

        if (held.speaking) {
            await capture.add('audio')
        }

        if (held.showing) {
            await capture.add('video')
        }
    })()
}()
