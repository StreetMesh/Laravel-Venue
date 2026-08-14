import { Client } from 'colyseus.js'
import peer from '../media/peer.js'
import signals from '../media/signals.js'
import { say, trouble } from '../media/log.js'

/**
 * Everybody in a party, connected to everybody else.
 *
 * The party's room in the hub says who is here — presence is the realtime
 * half's job and always has been. The venue carries the handshake over ordinary
 * HTTP, because it is a few messages that stop for good once two browsers have
 * found each other, and because the room's transport caps a message at about
 * half of what a video offer needs.
 *
 * Framework-free, and that is deliberate rather than austere. This holds a
 * microphone and a set of peer connections that have to outlive every re-render
 * happening around them, and the surest way to guarantee that is to owe nothing
 * to whatever is doing the rendering.
 *
 * It draws nothing. What it does is call back when something changes, and the
 * thing that draws decides what that looks like.
 */

/**
 * How often to look for notes left for us.
 *
 * Settled is not idle: somebody turning a camera on is a fresh negotiation
 * arriving unannounced, so the slow pace is what a person waits through before
 * they are seen. The fast one is for while a connection is still being made,
 * which is the only time anybody is watching.
 */
const LOOKING_HARD = 500
const LOOKING = 1000

export default function mesh({ ticketUrl, signalsUrl, csrf, tracks, onPeople, onTrouble }) {
    const connections = new Map()
    const arriving = new Map()

    let room = null
    let post = null
    let session = ''
    let ice = []
    let stopped = false

    /** Who is here, as the thing drawing wants it. */
    const people = () =>
        [...connections.keys()].map((id) => ({
            session: id,
            name: connections.get(id).name,
            status: connections.get(id).status(),
            stream: connections.get(id).arriving,
            audio: arriving.get(id)?.audio ?? false,
            video: arriving.get(id)?.video ?? false,
        }))

    const changed = () => onPeople(people())

    const settled = () => [...connections.values()].every((one) => one.status() === 'connected')

    /**
     * Open a line to one other person.
     *
     * Politeness comes from comparing the two session identifiers, which gives
     * opposite answers on the two sides without either being told. Perfect
     * negotiation needs exactly one polite party and nothing else about who.
     */
    const connect = (id, name) => {
        arriving.set(id, { audio: false, video: false })

        const connection = peer({
            ice,
            polite: session < id,
            name,
            send: (note) => post.post(id, note),
            onTrack: (kind, live) => {
                const held = arriving.get(id)

                if (held) {
                    held[kind === 'audio' ? 'audio' : 'video'] = live
                }

                changed()
            },
            onStatus: changed,
        })

        connections.set(id, connection)

        connection.open()
        connection.carry(tracks())

        say(`mesh: opened a line to ${name}`)
    }

    const regard = (state) => {
        const present = []

        state.occupants?.forEach((who, id) => {
            if (id !== session) {
                present.push({ session: id, name: who.name })
            }
        })

        for (const { session: id, name } of present) {
            if (!connections.has(id)) {
                connect(id, name)
            }
        }

        for (const id of [...connections.keys()]) {
            if (!present.some((person) => person.session === id)) {
                connections.get(id).close()
                connections.delete(id)
                arriving.delete(id)
            }
        }

        changed()
    }

    return {
        async join() {
            let admitted

            try {
                const response = await fetch(ticketUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                })

                admitted = await response.json()

                if (!response.ok) {
                    throw new Error(admitted.error ?? 'refused')
                }
            } catch (refused) {
                trouble('could not get a way into the party', refused)
                onTrouble('Your party would not let you in.')

                return false
            }

            ice = admitted.ice ?? []

            try {
                room = await new Client(admitted.hub).joinOrCreate(
                    admitted.type.replaceAll('.', '_'),
                    { ticket: admitted.ticket, room: admitted.room },
                )
            } catch (refused) {
                trouble('could not join the party room', refused)
                onTrouble('Could not reach the party’s room.')

                return false
            }

            session = room.sessionId

            post = signals({
                url: signalsUrl,
                session,
                csrf,
                onNotes: async (notes) => {
                    for (const { from, data } of notes) {
                        /*
                         * A note from somebody the room has not mentioned yet
                         * is ordinary during a join, and the sender will try
                         * again. Answering it would mean opening a connection
                         * to somebody we cannot see, which is the one thing the
                         * ticket is for.
                         */
                        await connections.get(from)?.absorb(data)
                    }
                },
                pace: () => (settled() ? LOOKING : LOOKING_HARD),
            })

            post.start()
            room.onStateChange(regard)

            return true
        },

        /** Tell the party where this browser is, for a roster that shows it. */
        here(space) {
            room?.send('here', { space: space ?? '' })
        },

        /** Send every peer exactly what is being captured now. */
        carry() {
            for (const connection of connections.values()) {
                connection.carry(tracks())
            }
        },

        people,

        leave() {
            if (stopped) {
                return
            }

            stopped = true
            post?.stop()

            for (const connection of connections.values()) {
                connection.close()
            }

            connections.clear()
            arriving.clear()

            void room?.leave()
        },
    }
}
