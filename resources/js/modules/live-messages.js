import { api } from '../lib/api'
import { replay } from '../lib/replay'
import { insertBubble } from '../lib/thread'

export function initLiveMessages() {
    const header = document.querySelector('header[data-live-channel]')
    if (!header || !import.meta.env.VITE_PUSHER_APP_KEY) return

    import('../lib/echo').then(({ connectEcho }) => {
        const echo = connectEcho()
        if (!echo) return

        const channel = header.dataset.liveChannel
        const handled = new Set()
        let missedPushes = false

        const setLive = (live) => header.toggleAttribute('data-live-connected', live && !missedPushes)
        const markMissed = () => {
            missedPushes = true
            setLive(false)
        }

        const subscribe = () => echo.private(channel)
            .listen('.message.sent', (payload) => {
                if (handled.has(payload.id)) return
                handled.add(payload.id)

                receive(payload)
            })
            .subscribed(() => setLive(true))

        echo.connector.pusher.connection.bind('state_change', ({ previous, current }) => {
            if (previous === 'connected' && current !== 'connected') markMissed()
        })

        subscribe()
        window.addEventListener('pagehide', () => {
            markMissed()
            echo.leave(channel)
        })
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) subscribe()
        })
    })
}

function receive(payload) {
    const thread = document.querySelector(`[data-live-thread="${payload.conversation_id}"]`)

    if (thread) {
        appendToThread(thread, payload)
        markRead(thread)
    } else {
        setUnreadBadges(payload.unread_count)
    }

    const list = document.querySelector('[data-live-list]')
    if (list) updateList(list, payload)
}

function appendToThread(thread, payload) {
    const list = thread.querySelector('ol')
    const template = thread.querySelector('template[data-live-bubble]')
    if (!list || !template || document.getElementById(`message-${payload.id}`)) return

    const endInView = list.getBoundingClientRect().bottom <= Math.min(thread.getBoundingClientRect().bottom, window.innerHeight) + 1
    const bubble = insertBubble(list, template, template.dataset.liveBubblePlaceholder, payload)

    if (!bubble || !endInView) return

    thread.scrollIntoView({ block: 'nearest' })
    thread.scrollTop = thread.scrollHeight
}

let readPending = false

function markRead(thread) {
    const post = () => api(thread.dataset.markReadUrl, { method: 'POST' }).catch(() => {})

    if (document.visibilityState === 'visible') {
        post()
        return
    }

    if (readPending) return
    readPending = true

    const onVisible = () => {
        if (document.visibilityState !== 'visible') return
        document.removeEventListener('visibilitychange', onVisible)
        readPending = false
        post()
    }

    document.addEventListener('visibilitychange', onVisible)
}

function setUnreadBadges(count) {
    document.querySelectorAll('[data-unread-badge]').forEach((badge) => {
        const text = badge.querySelector('[data-unread-badge-text]')
        if (text) text.textContent = badge.dataset.template.replace(':count', String(count))
        badge.hidden = count === 0
        replay(badge, 'data-bump')
    })

    document.querySelectorAll('[data-unread-dot]').forEach((dot) => {
        dot.hidden = count === 0
        replay(dot, 'data-bump')
    })
}

function updateList(list, payload) {
    const line = list.querySelector('[data-live-unread]')
    if (line) {
        line.textContent = payload.unread_count > 0 ? line.dataset.template.replace(':count', String(payload.unread_count)) : ''
    }

    const card = list.querySelector(`[data-conversation-card="${payload.conversation_id}"]`)
    if (!card) {
        showNotice(list)
        return
    }

    if (payload.id <= Number(card.dataset.lastMessageId)) return

    updateCard(card, payload)

    if (list.hasAttribute('data-first-page') && !card.contains(document.activeElement)) {
        card.parentElement.prepend(card)
    }
}

function updateCard(card, payload) {
    const message = card.querySelector('[data-card-message]')
    if (message) {
        message.textContent = payload.message
        message.removeAttribute('data-empty')
    }

    const time = card.querySelector('[data-card-time]')
    if (time) {
        time.dateTime = payload.created_at
        time.textContent = payload.created_at_human
        time.hidden = false
    }

    const count = Number(card.dataset.unreadCount) + 1
    card.dataset.unreadCount = String(count)
    card.dataset.lastMessageId = String(payload.id)
    card.setAttribute('data-unread', '')

    const counter = card.querySelector('[data-card-count]')
    if (counter) {
        const template = count === 1 ? counter.dataset.templateOne : counter.dataset.templateOther
        counter.textContent = ` ${template.replace(':count', String(count))}`
        card.querySelector('a')?.setAttribute('aria-describedby', counter.id)
    }
}

function showNotice(list) {
    const region = list.querySelector('[data-live-notice-region]')
    const template = list.querySelector('template[data-live-notice]')
    if (!region || !template || region.childElementCount > 0) return

    region.append(template.content.cloneNode(true))
}
