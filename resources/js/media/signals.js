import { say, trouble } from './log.js'

/**
 * The box each side leaves the other notes in.
 *
 * Two of them, at most, per connection: an offer and an answer, plus a handful
 * of addresses. Once two browsers have found each other there is nothing
 * further to say, and the audio and video go straight between them without
 * touching this or anything else on the server.
 *
 * It keeps looking anyway, slowly, because the other side can always reload and
 * want to start again.
 *
 * Polled, and that is the part of this worth replacing. The number of boxes to
 * watch grows with the size of the party, and the server this runs on has a
 * broadcast channel already configured.
 */
export default function signals({ url, session, csrf, onNotes, pace }) {
    let waiting = null
    let stopped = false
    let complaining = false

    /** Say it once, then stay quiet until it works again. */
    function report(what, error) {
        if (!complaining) {
            complaining = true
            trouble(what, error)
        }
    }

    async function collect() {
        const response = await fetch(`${url}?as=${encodeURIComponent(session)}`, {
            headers: { Accept: 'application/json' },
        })

        if (!response.ok) {
            throw new Error(`the venue answered ${response.status}`)
        }

        return (await response.json()).signals ?? []
    }

    async function look() {
        clearTimeout(waiting)

        try {
            const notes = await collect()

            complaining = false

            await onNotes(notes)
        } catch (error) {
            report('could not collect what was left for us', error)
        }

        if (!stopped) {
            waiting = setTimeout(look, pace())
        }
    }

    return {
        async post(to, note) {
            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ to, from: session, data: note }),
                })
            } catch (error) {
                report('could not leave a note', error)
            }
        },

        start() {
            say(`signalling as ${session}`)
            stopped = false
            void look()
        },

        /** Somebody arrived. Don't sit on the slow cadence waiting to notice. */
        hurry() {
            if (!stopped) {
                void look()
            }
        },

        stop() {
            stopped = true
            clearTimeout(waiting)
        },
    }
}
